#!/usr/bin/env python3
"""Loopback-only temporary browser harness. Tokens stay in process memory."""
import base64, hashlib, html, http.cookies, json, secrets, socket, time, os
from http.server import HTTPServer, BaseHTTPRequestHandler
from pathlib import Path
from urllib.parse import urlencode, urlsplit, parse_qs
from urllib.request import Request, build_opener, ProxyHandler
from urllib.error import HTTPError
from cryptography.hazmat.primitives.asymmetric import rsa, padding
from cryptography.hazmat.primitives import hashes
HOST='oneid-uat.upnm.edu.my'
ORIGIN='https://'+HOST
CLIENT='oneid-uat-controlled-browser'
CALLBACK=ORIGIN+'/mobile-test/callback'
UI_CSS=Path(__file__).with_name('harness-ui.css').read_text()
UI_STYLE_HASH=base64.b64encode(hashlib.sha256(UI_CSS.encode()).digest()).decode()
SESSIONS={}
MAX_SESSIONS=int(os.environ.get('ONEID_TEST_MAX_SESSIONS','20'))
OPENER=build_opener(ProxyHandler({}))
_original_resolver=socket.getaddrinfo

def local_resolver(host,*args,**kwargs):
    return _original_resolver('127.0.0.1' if host==HOST else host,*args,**kwargs)

def b64(raw): return base64.urlsafe_b64encode(raw).decode().rstrip('=')
def decode(value): return base64.urlsafe_b64decode(value+'='*(-len(value)%4))
def request(path,data=None,bearer=None):
    headers={}
    if bearer: headers['Authorization']='Bearer '+bearer
    if data is not None: headers['Content-Type']='application/x-www-form-urlencoded'
    req=Request(ORIGIN+path,data=urlencode(data).encode() if data is not None else None,headers=headers)
    try:
        with OPENER.open(req,timeout=15) as response:
            body=response.read(65536)
            return response.status,json.loads(body) if body else {}
    except HTTPError as error:
        try: body=json.loads(error.read(65536))
        except (ValueError,UnicodeError): body={}
        return error.code,body if isinstance(body,dict) else {}

def refresh_session(s):
    tokens=s['tokens']
    status,new=request('/oauth2/token',{'grant_type':'refresh_token','client_id':CLIENT,'refresh_token':tokens['refresh_token']})
    if status==200 and isinstance(new.get('access_token'),str) and new['access_token']:
        s['tokens']={**tokens,**new}
        return 'active'
    if status==400 and new.get('error')=='invalid_grant':
        for key in ('tokens','sub','message'):s.pop(key,None)
        return 'ended'
    return 'unavailable'

def logout_session(s):
    status,_=request('/mobile/logout',{},s['tokens']['access_token'])
    if status==401:
        outcome=refresh_session(s)
        if outcome=='ended':return 'Sesi ujian telah tamat; sila login semula. Logout server tidak disahkan.'
        if outcome!='active':return 'Server belum dapat mengesahkan sesi. Cuba logout semula sebentar lagi.'
        status,_=request('/mobile/logout',{},s['tokens']['access_token'])
    if status!=204:return 'Logout belum disahkan; HTTP '+str(status)+'. Cuba semula sebentar lagi.'
    tokens=s.pop('tokens');s.pop('sub',None);s.pop('message',None)
    status,body=request('/oauth2/token',{'grant_type':'refresh_token','client_id':CLIENT,'refresh_token':tokens['refresh_token']})
    if status==400 and body.get('error')=='invalid_grant':return 'Logout berjaya; refresh lama ditolak.'
    return 'Logout disahkan; keputusan penolakan refresh masih perlu disemak.'

def verify(token,nonce):
    parts=token.split('.')
    if len(parts)!=3: raise ValueError('token shape')
    header=json.loads(decode(parts[0]));claims=json.loads(decode(parts[1]))
    if header.get('alg')!='RS256': raise ValueError('algorithm')
    status,jwks=request('/.well-known/jwks.json')
    keys=[k for k in jwks.get('keys',[]) if k.get('kid')==header.get('kid') and k.get('kty')=='RSA' and k.get('use','sig')=='sig']
    if status!=200 or len(keys)!=1: raise ValueError('key')
    key=keys[0]
    public=rsa.RSAPublicNumbers(int.from_bytes(decode(key['e']),'big'),int.from_bytes(decode(key['n']),'big')).public_key()
    public.verify(decode(parts[2]),(parts[0]+'.'+parts[1]).encode(),padding.PKCS1v15(),hashes.SHA256())
    audience=claims.get('aud'); audience=[audience] if isinstance(audience,str) else audience
    now=time.time()
    if claims.get('iss')!=ORIGIN+'/' or not isinstance(audience,list) or CLIENT not in audience or not claims.get('sub'): raise ValueError('identity')
    if (len(audience)>1 or 'azp' in claims) and claims.get('azp')!=CLIENT: raise ValueError('authorized party')
    if claims.get('nonce')!=nonce or not isinstance(claims.get('exp'),(int,float)) or claims['exp']<=now: raise ValueError('expiry/nonce')
    if not isinstance(claims.get('iat'),(int,float)) or claims['iat']>now+30 or claims.get('nbf',0)>now+30: raise ValueError('time')
    return claims

class Handler(BaseHTTPRequestHandler):
    def log_message(self,*args): pass
    def reply(self,text,status=200,location=None,cookie=None):
        body=('<!doctype html><html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ujian OneID staging</title><style>'+UI_CSS+'</style></head><body><main><header><img src="/img/logo_upnm_30.png" alt="UPNM"><img src="/img/logo_oneid.png" alt="OneID"></header><div class="content"><span class="badge">Persekitaran Ujian</span><h1>Ujian login OneID</h1><p>Semak aliran login aplikasi menggunakan akaun OneID anda.</p>'+text+'</div><footer>OneID · UPNM &nbsp; / &nbsp; Staging</footer></main></body></html>').encode()
        self.send_response(status)
        for k,v in {'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store','Referrer-Policy':'no-referrer','X-Frame-Options':'DENY','Content-Security-Policy':"default-src 'none'; img-src 'self'; style-src 'sha256-"+UI_STYLE_HASH+"'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'"}.items(): self.send_header(k,v)
        if location:self.send_header('Location',location)
        if cookie:self.send_header('Set-Cookie',cookie)
        self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
    def session(self):
        cookies=http.cookies.SimpleCookie(self.headers.get('Cookie',''))
        sid=cookies.get('__Secure-oneid_pilot')
        entry=SESSIONS.get(sid.value) if sid else None
        return entry if entry and entry['expires']>time.time() else None
    def page(self,s,message=''):
        actions=[('start','Login dengan OneID','Mulakan pengesahan menggunakan password atau MyDigital ID.','primary'),('status','Semak sesi','Sahkan sesi semasa masih diterima oleh OneID.',''),('refresh','Uji refresh','Uji pembaharuan token tanpa memasukkan password semula.',''),('logout','Logout','Tamatkan sesi dan semak penolakan refresh token lama.','logout')]
        cards=''.join('<section class="step"><h2><span class="number">'+str(i)+'</span>'+label+'</h2><p>'+description+'</p><form method="post" action="/mobile-test/'+action+'"><input type="hidden" name="csrf" value="'+html.escape(s['csrf'],quote=True)+'"><button class="'+kind+'">'+label+'</button></form></section>' for i,(action,label,description,kind) in enumerate(actions,1))
        result=message or s.get('message','')
        notice='<section class="result" role="status"><strong>Keputusan ujian</strong><p>'+html.escape(result)+'</p></section>' if result else ''
        self.reply(notice+'<div class="steps">'+cards+'</div><p class="hint">Ikut langkah 1 hingga 4. Halaman ini menguji aliran OneID; ujian dalam aplikasi sebenar masih diperlukan.</p>')
    def do_GET(self):
        try:
            if self.headers.get('Host')!=HOST:return self.reply('Host tidak sah',403)
            path=urlsplit(self.path)
            if path.path=='/mobile-test/':
                for key in list(SESSIONS):
                    if SESSIONS[key]['expires']<=time.time():del SESSIONS[key]
                s=self.session()
                if s:return self.page(s)
                if len(SESSIONS)>=MAX_SESSIONS:return self.reply('Had sesi ujian dicapai',503)
                sid=secrets.token_urlsafe(32);s={'expires':time.time()+3600,'csrf':secrets.token_urlsafe(32)};SESSIONS[sid]=s
                return self.reply('<a class="open-link primary" href="/mobile-test/">Mulakan ujian login</a>',cookie='__Secure-oneid_pilot='+sid+'; Path=/mobile-test/; Secure; HttpOnly; SameSite=Lax')
            if path.path!='/mobile-test/callback':return self.reply('Tidak tersedia',404)
            s=self.session();q=parse_qs(path.query)
            if not s or not s.get('state') or not secrets.compare_digest(q.get('state',[''])[0],s.pop('state')):return self.reply('Callback tidak sah',400)
            code=q.get('code',[''])[0]
            if not code:return self.reply('Login dibatalkan. <a href="/mobile-test/">Kembali</a>',400)
            status,tokens=request('/oauth2/token',{'grant_type':'authorization_code','client_id':CLIENT,'redirect_uri':CALLBACK,'code':code,'code_verifier':s.pop('verifier')})
            if status!=200:raise ValueError('exchange')
            claims=verify(tokens['id_token'],s.pop('nonce'))
            if tokens.get('token_type','').lower()!='bearer' or not tokens.get('refresh_token'):raise ValueError('token types')
            status,profile=request('/mobile/session',bearer=tokens['access_token'])
            if status!=200 or profile.get('sub')!=claims['sub']:raise ValueError('session')
            s['tokens']=tokens;s['sub']=claims['sub'];s['message']='Login, PKCE, signature ID token dan sesi disahkan.'
            self.reply('Login, PKCE, signature dan sesi sah. <a href="/mobile-test/">Teruskan ujian</a>',303,location='/mobile-test/')
        except Exception:self.reply('Ujian tidak berjaya. Tiada token dipaparkan. Kembali ke /mobile-test/ untuk cuba semula.',400)
    def do_POST(self):
        try:
            if self.headers.get('Host')!=HOST or self.headers.get('Origin')!=ORIGIN:return self.reply('Origin ditolak',403)
            s=self.session();length=int(self.headers.get('Content-Length','0'))
            if not s or not 0<=length<=2048:return self.reply('Permintaan ditolak',400)
            fields=parse_qs(self.rfile.read(length).decode())
            if not secrets.compare_digest(fields.get('csrf',[''])[0],s['csrf']):return self.reply('CSRF ditolak',403)
            s['csrf']=secrets.token_urlsafe(32)
            action=urlsplit(self.path).path
            if action=='/mobile-test/start':
                if s.get('tokens'):
                    status,_=request('/mobile/session',bearer=s['tokens']['access_token'])
                    if status==401:
                        outcome=refresh_session(s)
                        if outcome=='unavailable':return self.page(s,'Server belum dapat mengesahkan sesi. Cuba semula sebentar lagi.')
                    elif status!=200:return self.page(s,'Semakan sesi belum berjaya; HTTP '+str(status))
                    if s.get('tokens'):return self.page(s,'Sesi ujian masih aktif. Klik Logout sebelum login baharu.')
                s.update(state=secrets.token_urlsafe(32),nonce=secrets.token_urlsafe(32),verifier=secrets.token_urlsafe(48))
                params={'client_id':CLIENT,'redirect_uri':CALLBACK,'response_type':'code','scope':'openid profile offline_access mobile:session','audience':'oneid-mobile-session','state':s['state'],'nonce':s['nonce'],'code_challenge':b64(hashlib.sha256(s['verifier'].encode()).digest()),'code_challenge_method':'S256'}
                return self.reply('Membuka OneID',303,location=ORIGIN+'/oauth2/auth?'+urlencode(params))
            tokens=s.get('tokens')
            if not tokens:return self.page(s,'Login dahulu.')
            if action=='/mobile-test/status':
                status,profile=request('/mobile/session',bearer=tokens['access_token'])
                return self.page(s,'Sesi aktif disahkan.' if status==200 and profile.get('sub')==s['sub'] else 'Sesi belum disahkan; HTTP '+str(status))
            if action=='/mobile-test/refresh':
                outcome=refresh_session(s)
                if outcome=='ended':return self.page(s,'Sesi ujian telah tamat; sila login semula.')
                if outcome!='active':return self.page(s,'Refresh belum berjaya; cuba semula sebentar lagi.')
                status,profile=request('/mobile/session',bearer=s['tokens']['access_token'])
                return self.page(s,'Refresh dan sesi berjaya.' if status==200 and profile.get('sub')==s['sub'] else 'Semakan sesi selepas refresh gagal.')
            if action=='/mobile-test/logout':
                return self.page(s,logout_session(s))
            self.reply('Tidak tersedia',404)
        except Exception:self.reply('Ujian terganggu. Tiada token dipaparkan.',503)

if __name__=='__main__':
    socket.getaddrinfo=local_resolver
    HTTPServer(('127.0.0.1',24147),Handler).serve_forever()

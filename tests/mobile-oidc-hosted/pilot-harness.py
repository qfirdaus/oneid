import importlib.util,json,time,base64
from pathlib import Path
from cryptography.hazmat.primitives.asymmetric import rsa,padding
from cryptography.hazmat.primitives import hashes
p=Path(__file__).resolve().parents[2]/'tools/mobile-oidc-hosted/pilot-harness.py'
s=importlib.util.spec_from_file_location('harness',p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
k=rsa.generate_private_key(public_exponent=65537,key_size=2048);n=k.public_key().public_numbers()
b=lambda i:m.b64(i.to_bytes((i.bit_length()+7)//8,'big'))
m.request=lambda path:(200,{'keys':[{'kid':'fixture','kty':'RSA','n':b(n.n),'e':b(n.e)}]})
def token(claims):
 h=m.b64(json.dumps({'alg':'RS256','kid':'fixture'}).encode());c=m.b64(json.dumps(claims).encode());body=(h+'.'+c).encode();return h+'.'+c+'.'+m.b64(k.sign(body,padding.PKCS1v15(),hashes.SHA256()))
c={'iss':m.ORIGIN+'/','aud':m.CLIENT,'sub':'synthetic','nonce':'nonce','iat':time.time(),'exp':time.time()+60}
assert m.verify(token(c),'nonce')['sub']=='synthetic'
for field,value in [('iss','https://wrong.invalid/'),('aud','wrong'),('exp',0),('nonce','wrong'),('iat',time.time()+1000),('azp','wrong')]:
 try:m.verify(token({**c,field:value}),'nonce')
 except ValueError:pass
 else:raise AssertionError(field)
t=token(c);parts=t.split('.');sig=bytearray(m.decode(parts[2]));sig[0]^=1
try:m.verify('.'.join(parts[:2])+'.'+m.b64(sig),'nonce')
except Exception:pass
else:raise AssertionError('signature')
print('PASS: valid signed ID token; wrong issuer/audience/expiry/nonce/iat/azp and bad signature rejected (8 checks)')
# Synthetic expired-token recovery; no real provider requests.
def run_logout(responses):
 calls=[]
 def fake(path,data=None,bearer=None):
  calls.append((path,data,bearer));return responses.pop(0)
 m.request=fake
 state={'tokens':{'access_token':'old','refresh_token':'refresh-old'},'sub':'synthetic','message':'old success'}
 result=m.logout_session(state)
 return state,result,calls
state,msg,calls=run_logout([(401,{}),(200,{'access_token':'new','refresh_token':'rotated'}),(204,{}),(400,{'error':'invalid_grant'})])
assert 'tokens' not in state and 'refresh lama ditolak' in msg
assert calls[2][2]=='new' and calls[3][1]['refresh_token']=='rotated'
state,msg,_=run_logout([(401,{}),(400,{'error':'invalid_grant'})])
assert 'tokens' not in state and 'tidak disahkan' in msg
state,msg,_=run_logout([(401,{}),(503,{})])
assert state['tokens']['refresh_token']=='refresh-old' and 'belum' in msg
state,msg,_=run_logout([(503,{})])
assert 'tokens' in state and 'belum disahkan' in msg
state,msg,_=run_logout([(204,{}),(503,{})])
assert 'tokens' not in state and 'masih perlu disemak' in msg
print('PASS: expired access recovery, rotated refresh, ended session, transient failures and unconfirmed refresh rejection (5 scenarios)')

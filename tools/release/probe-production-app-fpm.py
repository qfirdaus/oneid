#!/usr/bin/env python3
import socket, struct, json

def enc(n): return bytes([n]) if n < 128 else struct.pack('!I', n | 0x80000000)

def main():
    params = {'SCRIPT_FILENAME':'/var/www/oneid/public/index.php','REQUEST_METHOD':'GET','REQUEST_URI':'/','QUERY_STRING':'','SERVER_PROTOCOL':'HTTP/1.1','GATEWAY_INTERFACE':'CGI/1.1','REMOTE_ADDR':'127.0.0.1','SERVER_NAME':'oneid.upnm.edu.my','HTTP_HOST':'oneid.upnm.edu.my','HTTPS':'on'}
    data = b''.join(enc(len(k.encode()))+enc(len(v.encode()))+k.encode()+v.encode() for k,v in params.items())
    def rec(kind, payload): return struct.pack('!BBHHBB',1,kind,1,len(payload),0,0)+payload
    with socket.socket(socket.AF_UNIX,socket.SOCK_STREAM) as s:
        s.settimeout(30); s.connect('/run/php/oneid-web-prod84.sock')
        s.sendall(rec(1,struct.pack('!HB5x',1,0))+rec(4,data)+rec(4,b'')+rec(5,b'')); out=b''; err=b''
        while True:
            h=s.recv(8)
            if len(h)!=8: raise RuntimeError('truncated FastCGI response')
            _,kind,_,length,pad,_=struct.unpack('!BBHHBB',h); payload=s.recv(length) if length else b''
            if kind==6: out+=payload
            if kind==7: err+=payload
            if pad: s.recv(pad)
            if kind==3: break
    if err: raise RuntimeError(err.decode(errors='replace')[:500])
    head,body=out.split(b'\r\n\r\n',1); text=body.decode(errors='replace')
    print(json.dumps({'status':'PASS','socket':'oneid-web-prod84.sock','header':head.decode(errors='replace')[:1000],'body_bytes':len(body),'has_login_marker':'Login' in text or 'Log Masuk' in text,'contains_php_error':'Fatal error' in text or 'Parse error' in text},indent=2))

if __name__=='__main__': main()

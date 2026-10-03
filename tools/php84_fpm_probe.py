#!/usr/bin/env python3
"""Read-only runtime probe via local FPM; temporary PHP stays outside web roots."""
import json, os, socket, struct, tempfile
from pathlib import Path

def request_source(path, source, include_stderr=False):
    fd,name=tempfile.mkstemp(prefix='oneid-fpm-probe-',suffix='.php')
    try:
        os.write(fd,source.encode());os.close(fd);os.chmod(name,0o644)
        def record(kind,data):return struct.pack('!BBHHBB',1,kind,1,len(data),0,0)+data
        def size(n):return bytes([n]) if n<128 else struct.pack('!I',n|0x80000000)
        params={'SCRIPT_FILENAME':name,'REQUEST_METHOD':'GET','SERVER_PROTOCOL':'HTTP/1.1','GATEWAY_INTERFACE':'CGI/1.1','REMOTE_ADDR':'127.0.0.1','SERVER_NAME':'localhost'}
        data=b''
        for k,v in params.items():
            k=k.encode();v=v.encode();data+=size(len(k))+size(len(v))+k+v
        with socket.socket(socket.AF_UNIX,socket.SOCK_STREAM) as sock:
            sock.settimeout(60);sock.connect(path);sock.sendall(record(1,struct.pack('!HB5x',1,0))+record(4,data)+record(4,b'')+record(5,b''))
            def read(n):
                b=b''
                while len(b)<n:
                    x=sock.recv(n-len(b))
                    if not x:raise RuntimeError('Truncated FastCGI response')
                    b+=x
                return b
            out=b'';err=b''
            while True:
                _,kind,_,length,padding,_=struct.unpack('!BBHHBB',read(8));payload=read(length);read(padding)
                if kind==6:out+=payload
                if kind==7:err+=payload
                if kind==3:break
            if include_stderr:
                return out.split(b'\r\n\r\n',1)[1].decode(errors='replace'), err.decode(errors='replace')
            if err:raise RuntimeError('FPM stderr: '+err.decode(errors='replace')[:300])
            return out.split(b'\r\n\r\n',1)[1].decode(errors='replace')
    finally:os.unlink(name)
def probe(path):
    source='<?php header("Content-Type: application/json"); echo json_encode(["version"=>PHP_VERSION,"sapi"=>PHP_SAPI,"extensions"=>get_loaded_extensions(),"settings"=>array_combine($k=["memory_limit","max_execution_time","post_max_size","upload_max_filesize","date.timezone","session.gc_maxlifetime","session.cookie_secure","session.cookie_httponly","display_errors","error_reporting"],array_map("ini_get",$k))]);'
    return json.loads(request_source(path, source))

if __name__=='__main__':
    results={name:probe('/run/php/'+name+'.sock') for name in ['php8.3-fpm','oneid-mobile-uat','oneid-web-uat84','oneid-mobile-uat84']}
    print(json.dumps(results,indent=2))

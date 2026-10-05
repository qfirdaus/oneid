#!/usr/bin/env python3
"""Read-only FastCGI smoke against production PHP 8.4 pools."""
import json, os, socket, struct, tempfile

def request(path):
    fd, name = tempfile.mkstemp(prefix='oneid-fpm-probe-', suffix='.php')
    try:
        os.write(fd, b'<?php header("Content-Type: application/json"); echo json_encode(["version"=>PHP_VERSION,"sapi"=>PHP_SAPI,"settings"=>array_combine($k=["memory_limit","max_execution_time","post_max_size","upload_max_filesize","date.timezone","session.gc_maxlifetime","session.cookie_secure","session.cookie_httponly","display_errors"],array_map("ini_get",$k))]);')
        os.close(fd); os.chmod(name, 0o644)
        def record(kind, data): return struct.pack('!BBHHBB', 1, kind, 1, len(data), 0, 0) + data
        def size(n): return bytes([n]) if n < 128 else struct.pack('!I', n | 0x80000000)
        params = {'SCRIPT_FILENAME': name, 'REQUEST_METHOD': 'GET', 'SERVER_PROTOCOL': 'HTTP/1.1', 'GATEWAY_INTERFACE': 'CGI/1.1', 'REMOTE_ADDR': '127.0.0.1', 'SERVER_NAME': 'localhost'}
        data = b''.join(size(len(k.encode())) + size(len(v.encode())) + k.encode() + v.encode() for k, v in params.items())
        with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as sock:
            sock.settimeout(30); sock.connect(path)
            sock.sendall(record(1, struct.pack('!HB5x', 1, 0)) + record(4, data) + record(4, b'') + record(5, b''))
            body = b''; errors = b''
            while True:
                header = sock.recv(8)
                if len(header) != 8: raise RuntimeError('truncated FastCGI header')
                _, kind, _, length, padding, _ = struct.unpack('!BBHHBB', header)
                payload = sock.recv(length) if length else b''
                if kind == 6: body += payload
                if kind == 7: errors += payload
                if padding: sock.recv(padding)
                if kind == 3: break
            if errors: raise RuntimeError(errors.decode(errors='replace')[:500])
            return json.loads(body.split(b'\r\n\r\n', 1)[1])
    finally:
        os.unlink(name)

if __name__ == '__main__':
    result = {'scope': 'read-only FastCGI smoke; no Nginx, code, DB, cron or alternatives changes', 'pools': {}}
    for name in ['oneid-web-prod84', 'oneid-mobile-prod84']:
        result['pools'][name] = request('/run/php/' + name + '.sock')
    print(json.dumps(result, indent=2))

import paramiko, sys
import os, sys as _s
_s.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import deploy_creds as creds  # noqa: E402
H,U,_,P = creds.conn("gpu166")
c=paramiko.SSHClient(); c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(H,22,U,P,timeout=40,look_for_keys=False,allow_agent=False)
def sudo(cmd, t=120):
    ch=c.get_transport().open_session(); ch.settimeout(t)
    ch.exec_command("sudo -S bash -s")
    ch.sendall(P+"\n"+cmd+"\n"); ch.shutdown_write()
    out=b""
    try:
        while True:
            d=ch.recv(65536)
            if not d: break
            out+=d
    except Exception as e:
        out+=("\n[[TIMEOUT %s]]"%e).encode()
    return out.decode("utf-8","replace")
if __name__=="__main__":
    print(sudo(sys.stdin.read()))

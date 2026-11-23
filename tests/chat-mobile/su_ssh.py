"""sugus 直连工具（密码认证失效时用 ~/.ssh/douyin_38_181 私钥）。

用法:
    from su_ssh import sftp, run
    sftp.put(local, remote)   /  run("cmd") -> (rc, out, err)
"""
import pathlib
import paramiko

HOST = "172.81.98.55"
USER = "root"
KEY = pathlib.Path.home() / ".ssh" / "douyin_38_181"


def _pkey():
    for cls in (paramiko.Ed25519Key, paramiko.RSAKey, paramiko.ECDSAKey):
        try:
            return cls.from_private_key_file(str(KEY))
        except Exception:
            continue
    raise RuntimeError("no usable key")


def client():
    cli = paramiko.SSHClient()
    cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    cli.connect(HOST, 22, USER, pkey=_pkey(), timeout=25,
                look_for_keys=False, allow_agent=False)
    return cli


def run(cmd, timeout=120, cli=None):
    own = cli is None
    cli = cli or client()
    try:
        _, out, err = cli.exec_command(cmd, timeout=timeout)
        rc = out.channel.recv_exit_status()
        return rc, out.read().decode("utf-8", "replace"), err.read().decode("utf-8", "replace")
    finally:
        if own:
            cli.close()

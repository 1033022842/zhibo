"""
ffmpeg zmq 命令发送工具
用法: python zmq_cmd.py <port> <command1> [command2 ...]
示例: python zmq_cmd.py 5505 "Parsed_movie_0 filename 'D:/path/to/gift.mp4'" "Parsed_overlay_0 enable 1"
"""
import zmq
import sys
import time

def send_cmds(port: int, commands: list):
    ctx = zmq.Context()
    sock = ctx.socket(zmq.REQ)
    sock.setsockopt(zmq.LINGER, 0)
    sock.setsockopt(zmq.RCVTIMEO, 1000)
    sock.setsockopt(zmq.SNDTIMEO, 1000)
    sock.connect(f"tcp://127.0.0.1:{port}")
    
    for cmd in commands:
        try:
            sock.send_string(cmd)
            # ffmpeg zmq filter replies to each command
            reply = sock.recv_string()
            print(f"OK: {cmd[:60]} -> {reply}")
        except zmq.ZMQError as e:
            print(f"ERR: {cmd[:60]} -> {e}", file=sys.stderr)
    
    sock.close()
    ctx.term()

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Usage: python zmq_cmd.py <port> <command1> [command2 ...]")
        sys.exit(1)
    port = int(sys.argv[1])
    commands = sys.argv[2:]
    send_cmds(port, commands)

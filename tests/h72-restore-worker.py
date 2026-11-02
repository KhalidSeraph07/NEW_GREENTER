"""Punto de interrupción del ensayo; contraseña solo por stdin, nunca argv/entorno."""
import sys
import time
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tools'))
from h72_recovery import restore

archive, target, project, keys, checkpoint = sys.argv[1:]
password = sys.stdin.buffer.readline().rstrip(b'\n')


def progress(stage):
    if stage == 'restored_emisor_h1':
        Path(checkpoint).write_text('DB1 confirmada; DB2 pendiente; destino aún bloqueado.\n')
        while True: time.sleep(0.1)


restore(Path(archive), Path(target), project, 8197, Path(keys), password, progress)

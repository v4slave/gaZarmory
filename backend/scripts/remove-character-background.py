from __future__ import annotations

import io
import sys
from pathlib import Path

from PIL import Image, ImageOps
from rembg import new_session, remove


def main() -> None:
    if len(sys.argv) != 4:
        raise SystemExit("usage: remove-character-background.py INPUT OUTPUT MODEL")

    source, destination, model_name = Path(sys.argv[1]), Path(sys.argv[2]), sys.argv[3]
    with Image.open(source) as opened:
        image = ImageOps.exif_transpose(opened).convert("RGB")
        image.thumbnail((2560, 2560), Image.Resampling.LANCZOS)
        prepared = io.BytesIO()
        image.save(prepared, format="PNG", optimize=True)

    result = remove(prepared.getvalue(), session=new_session(model_name))
    destination.write_bytes(result)


if __name__ == "__main__":
    main()

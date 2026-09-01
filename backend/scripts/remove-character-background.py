from __future__ import annotations

import io
import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageOps
from rembg import new_session, remove
from scipy import ndimage


def crop_to_content(image: Image.Image) -> Image.Image:
    bounds = image.getchannel("A").getbbox()
    if not bounds:
        raise RuntimeError("No foreground was detected")

    left, top, right, bottom = bounds
    padding = max(12, round(max(right - left, bottom - top) * 0.04))
    return image.crop((
        max(0, left - padding),
        max(0, top - padding),
        min(image.width, right + padding),
        min(image.height, bottom + padding),
    ))


def clean_and_crop(result: bytes) -> Image.Image:
    image = Image.open(io.BytesIO(result)).convert("RGBA")
    alpha = np.asarray(image.getchannel("A"))
    foreground = alpha >= 32
    labels, count = ndimage.label(foreground, structure=np.ones((3, 3), dtype=np.uint8))

    if count:
        sizes = np.bincount(labels.ravel())
        sizes[0] = 0
        main_component = labels == int(sizes.argmax())
        protected = ndimage.binary_dilation(main_component, iterations=3)
        cleaned_alpha = np.where(protected, alpha, 0).astype(np.uint8)
        image.putalpha(Image.fromarray(cleaned_alpha))

    return crop_to_content(image)


def main() -> None:
    if len(sys.argv) != 4:
        raise SystemExit("usage: remove-character-background.py INPUT OUTPUT MODEL")

    source, destination, model_name = Path(sys.argv[1]), Path(sys.argv[2]), sys.argv[3]
    with Image.open(source) as opened:
        image = ImageOps.exif_transpose(opened)
        if "A" in image.getbands() and image.getchannel("A").getextrema()[0] < 255:
            crop_to_content(image.convert("RGBA")).save(destination, format="PNG", optimize=True)
            return

        image = image.convert("RGB")
        image.thumbnail((2560, 2560), Image.Resampling.LANCZOS)
        prepared = io.BytesIO()
        image.save(prepared, format="PNG", optimize=True)

    result = remove(prepared.getvalue(), session=new_session(model_name))
    clean_and_crop(result).save(destination, format="PNG", optimize=True)


if __name__ == "__main__":
    main()

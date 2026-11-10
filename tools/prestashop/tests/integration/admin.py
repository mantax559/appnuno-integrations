import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[4] / "tools" / "shared" / "tests"))

from host import PrestaShopAdapter
from integration.scenario import run


def main() -> None:
    run(PrestaShopAdapter())


if __name__ == "__main__":
    main()

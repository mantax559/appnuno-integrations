from integration.config import load_runtime
from integration.scenario import run


def main() -> None:
    run(load_runtime())


if __name__ == "__main__":
    main()

import os
from dataclasses import dataclass
from pathlib import Path


ADMIN_PASSWORD: str = "opencart-ci-password"
ADMIN_USER: str = "admin"
BASE_URL: str = "http://127.0.0.1:8080"
FTP_PASSWORD: str = "opencart-ci-password"
FTP_PORT: int = 2121
FTP_USER: str = "opencart-ci"
# OpenCart 3 release assets use inconsistent names; 3.0.0.2 has a tag but no release archive.
OPENCART_30_ASSETS: dict[str, str] = {
    "3.0.0.0": "3.0.0.0-compiled.zip",
    "3.0.0.2": "",
    "3.0.1.1": "3.0.1.1-compiled.zip",
    "3.0.1.2": "3.0.1.2-opencart.zip",
    "3.0.2.0": "3.0.2.0-OpenCart.zip",
    "3.0.3.0": "opencart-3.0.3.0.zip",
    "3.0.3.1": "opencart-3.0.3.1.zip",
    "3.0.3.2": "opencart-3.0.3.2.zip",
    "3.0.3.3": "opencart-3.0.3.3.zip",
    "3.0.3.4": "opencart-3.0.3.4-core-pre.zip",
    "3.0.3.5": "opencart-3.0.3.5.zip",
    "3.0.3.6": "opencart-3.0.3.6.zip",
    "3.0.3.7": "opencart-3.0.3.7.zip",
    "3.0.3.8": "opencart-3.0.3.8.zip",
    "3.0.3.9": "opencart-3.0.3.9.zip",
    "3.0.4.0": "opencart-3.0.4.0.zip",
    "3.0.4.1": "3.0.4.1.zip",
    "3.0.5.0": "opencart-3.0.5.0.zip",
    "3.0.5.1": "opencart-3.0.5.1.zip",
}
REPOSITORY: Path = Path(__file__).resolve().parents[4]
SUPPORTED_OPENCART_VERSIONS: set[str] = {
    "2.0.0.0", "2.0.1.0", "2.0.1.1", "2.0.2.0", "2.0.3.0", "2.0.3.1",
    "2.1.0.0", "2.1.0.1", "2.1.0.2", "2.2.0.0", "2.3.0.0", "2.3.0.1", "2.3.0.2",
    "3.0.0.0", "3.0.0.2", "3.0.1.1", "3.0.1.2", "3.0.2.0", "3.0.3.0", "3.0.3.1",
    "3.0.3.2", "3.0.3.3", "3.0.3.4", "3.0.3.5", "3.0.3.6", "3.0.3.7", "3.0.3.8",
    "3.0.3.9", "3.0.4.0", "3.0.4.1", "3.0.5.0", "3.0.5.1", "4.0.0.0", "4.0.1.0",
    "4.0.1.1", "4.0.2.0", "4.0.2.1", "4.0.2.2", "4.0.2.3", "4.1.0.0", "4.1.0.1",
    "4.1.0.2", "4.1.0.3", "4.1.0.4",
}


@dataclass(frozen=True)
class Runtime:
    admin_password: str
    admin_user: str
    archive_url: str
    base_url: str
    db_host: str
    db_name: str
    db_password: str
    db_user: str
    ftp_password: str
    ftp_port: int
    ftp_user: str
    line: str
    package: Path
    release_line: str
    repository: Path
    sdk_source: str
    version: str


def load_runtime() -> Runtime:
    version = os.environ.get("OPENCART_VERSION", "2.3.0.2")
    if version not in SUPPORTED_OPENCART_VERSIONS:
        raise ValueError(f"Unsupported OpenCart test version: {version}")
    line = version[:3]
    release_line = "3" if line == "3.0" else line
    package = REPOSITORY / "downloads" / "opencart" / f"{release_line}.x" / f"appnuno-opencart-{release_line}.x.ocmod.zip"
    if line in ("2.0", "2.1") or (line == "3.0" and OPENCART_30_ASSETS[version] == ""):
        archive_url = f"https://github.com/opencart/opencart/archive/refs/tags/{version}.zip"
    elif line == "3.0":
        archive_url = f"https://github.com/opencart/opencart/releases/download/{version}/{OPENCART_30_ASSETS[version]}"
    elif version.startswith("4."):
        # OpenCart 4 uses the uploaded filename as its extension code.
        package = REPOSITORY / "downloads" / "opencart" / "4.x" / "appnuno.ocmod.zip"
        archive_url = f"https://github.com/opencart/opencart/releases/download/{version}/opencart-{version}.zip"
    else:
        archive_url = f"https://github.com/opencart/opencart/releases/download/{version}/{version}-compiled.zip"
    sdk_path = Path(os.environ.get("APPNUNO_SDK_SOURCE", str(REPOSITORY.parent / "appnuno-web" / "public" / "sdk" / "v1" / "appnuno.js")))
    return Runtime(
        admin_password=ADMIN_PASSWORD,
        admin_user=ADMIN_USER,
        archive_url=archive_url,
        base_url=BASE_URL,
        db_host=os.environ.get("OPENCART_DB_HOST", "127.0.0.1"),
        db_name=os.environ.get("OPENCART_DB_NAME", "opencart"),
        db_password=os.environ.get("OPENCART_DB_PASSWORD", "root"),
        db_user=os.environ.get("OPENCART_DB_USER", "root"),
        ftp_password=FTP_PASSWORD,
        ftp_port=FTP_PORT,
        ftp_user=FTP_USER,
        line=line,
        package=package,
        release_line=release_line,
        repository=REPOSITORY,
        sdk_source=sdk_path.read_text(encoding="utf-8"),
        version=version,
    )

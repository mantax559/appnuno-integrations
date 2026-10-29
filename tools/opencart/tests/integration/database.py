from collections.abc import Iterator
from contextlib import contextmanager

import pymysql
from pymysql.connections import Connection

from integration.compatibility import opencart4, opencart_version_parts, setting_scope_column
from integration.config import Runtime


@contextmanager
def connect(runtime: Runtime, autocommit: bool = False) -> Iterator[Connection]:
    connection = pymysql.connect(
        host=runtime.db_host,
        user=runtime.db_user,
        password=runtime.db_password,
        database=runtime.db_name,
        autocommit=autocommit,
    )
    try:
        yield connection
    finally:
        connection.close()


def appnuno_event_count(runtime: Runtime) -> int:
    with connect(runtime) as connection:
        with connection.cursor() as cursor:
            cursor.execute("SELECT COUNT(*) FROM oc_event WHERE code = 'appnuno'")
            count = cursor.fetchone()[0]
    return count


def configuration_value(runtime: Runtime, key: str) -> str | None:
    with connect(runtime) as connection:
        with connection.cursor() as cursor:
            cursor.execute(f"SELECT value FROM oc_setting WHERE store_id = 0 AND {setting_scope_column(runtime)} = 'config' AND `key` = %s", (key,))
            row = cursor.fetchone()
    return row[0] if row else None


def configure_ftp(runtime: Runtime) -> None:
    with connect(runtime, autocommit=True) as connection:
        with connection.cursor() as cursor:
            values = {
                "config_ftp_status": "1",
                "config_ftp_hostname": "127.0.0.1",
                "config_ftp_port": str(runtime.ftp_port),
                "config_ftp_username": runtime.ftp_user,
                "config_ftp_password": runtime.ftp_password,
                "config_ftp_root": ""
            }
            for key, value in values.items():
                cursor.execute(f"UPDATE oc_setting SET value = %s WHERE store_id = 0 AND {setting_scope_column(runtime)} = 'config' AND `key` = %s", (value, key))


def create_cart_product(runtime: Runtime) -> int:
    with connect(runtime, autocommit=True) as connection:
        with connection.cursor() as cursor:
            cursor.execute("SELECT stock_status_id FROM oc_stock_status ORDER BY stock_status_id LIMIT 1")
            stock_status_id = cursor.fetchone()[0]
            if opencart4(runtime):
                cursor.execute("SHOW COLUMNS FROM oc_product")
                product_columns = {row[0] for row in cursor.fetchall()}
                assignments = []
                for column, value in (
                    ("master_id", "0"),
                    ("model", "'APPNUNO-CI-PRODUCT'"),
                    ("manufacturer_id", "0"),
                    ("quantity", "50"),
                    ("stock_status_id", str(stock_status_id)),
                    ("shipping", "1"),
                    ("price", "1.00"),
                    ("tax_class_id", "0"),
                    ("date_available", "CURDATE()"),
                    ("subtract", "1"),
                    ("minimum", "1"),
                    ("sort_order", "0"),
                    ("status", "1"),
                    ("viewed", "0"),
                    ("variant", "''"),
                ):
                    if column in product_columns:
                        assignments.append(f"`{column}` = {value}")
                if "date_added" in product_columns:
                    assignments.append("`date_added` = NOW()")
                if "date_modified" in product_columns:
                    assignments.append("`date_modified` = NOW()")
                cursor.execute("INSERT INTO oc_product SET " + ", ".join(assignments))
            else:
                cursor.execute(
                    "INSERT INTO oc_product (model, sku, upc, ean, jan, isbn, mpn, location, quantity, stock_status_id, image, manufacturer_id, shipping, price, points, tax_class_id, date_available, weight, weight_class_id, length, width, height, length_class_id, subtract, minimum, sort_order, status, viewed, date_added, date_modified) VALUES (%s, '', '', '', '', '', '', '', 50, %s, '', 0, 1, 1.00, 0, 0, CURDATE(), 0, 1, 0, 0, 0, 1, 1, 1, 0, 1, 0, NOW(), NOW())",
                    ("APPNUNO-CI-PRODUCT", stock_status_id),
                )
            product_id = cursor.lastrowid
            language_key = "config_language_catalog" if opencart4(runtime) and opencart_version_parts(runtime) >= (4, 1, 0, 0) else "config_language"
            cursor.execute(f"SELECT l.language_id FROM oc_language l INNER JOIN oc_setting s ON s.store_id = 0 AND s.`key` = '{language_key}' AND s.value = l.code LIMIT 1")
            language_row = cursor.fetchone()
            if not language_row or not language_row[0]:
                raise AssertionError("OpenCart has no store language, so the cart product cannot be displayed.")
            if opencart4(runtime):
                cursor.execute("SHOW COLUMNS FROM oc_product_description")
                description_columns = {row[0] for row in cursor.fetchall()}
                fields = ["product_id", "language_id", "name", "description"]
                params: list[object] = [product_id, language_row[0], "AppNuno CI Product", "Cart integration fixture"]
                for column, value in (("tag", ""), ("meta_title", "AppNuno CI Product"), ("meta_description", ""), ("meta_keyword", "")):
                    if column in description_columns:
                        fields.append(column)
                        params.append(value)
                cursor.execute(
                    "INSERT INTO oc_product_description (" + ", ".join(f"`{field}`" for field in fields) + ") VALUES (" + ", ".join(["%s"] * len(fields)) + ")",
                    params,
                )
            else:
                cursor.execute(
                    "INSERT INTO oc_product_description (product_id, language_id, name, description, tag, meta_title, meta_description, meta_keyword) VALUES (%s, %s, 'AppNuno CI Product', 'Cart integration fixture', '', 'AppNuno CI Product', '', '')",
                    (product_id, language_row[0]),
                )
            cursor.execute("INSERT INTO oc_product_to_store (product_id, store_id) VALUES (%s, 0)", (product_id,))
    return product_id


def remember_admin_language_code(runtime: Runtime, code: str) -> None:
    # OpenCart 4.0.2+ builds the admin language from language_code, which the settings form does not post.
    if not opencart4(runtime) or opencart_version_parts(runtime) < (4, 0, 2, 0):
        return
    with connect(runtime, autocommit=True) as connection:
        with connection.cursor() as cursor:
            cursor.execute("UPDATE oc_setting SET value = %s WHERE store_id = 0 AND `key` = 'language_code'", (code,))
            if cursor.rowcount == 0:
                cursor.execute("INSERT INTO oc_setting (store_id, code, `key`, value, serialized) VALUES (0, 'config', 'language_code', %s, 0)", (code,))


def repair_prerelease_customer_ip(runtime: Runtime) -> None:
    # The 3.0.3.4 pre-release installer omits store_id although its customer IP code expects it.
    if runtime.version != "3.0.3.4":
        return
    with connect(runtime, autocommit=True) as connection:
        with connection.cursor() as cursor:
            cursor.execute("ALTER TABLE oc_customer_ip ADD COLUMN store_id int(11) NOT NULL AFTER customer_id")


def setting_value(runtime: Runtime, key: str) -> str | None:
    with connect(runtime) as connection:
        with connection.cursor() as cursor:
            cursor.execute(f"SELECT value FROM oc_setting WHERE store_id = 0 AND {setting_scope_column(runtime)} = 'module_appnuno' AND `key` = %s", (key,))
            row = cursor.fetchone()
    return row[0] if row else None


def update_english_language_code(runtime: Runtime) -> None:
    with connect(runtime, autocommit=True) as connection:
        with connection.cursor() as cursor:
            cursor.execute("UPDATE oc_language SET code = 'en-gb' WHERE code = 'en' AND directory = 'english'")
            if cursor.rowcount != 1:
                raise AssertionError("OpenCart's default English language could not be changed to en-gb.")
            cursor.execute(f"UPDATE oc_setting SET value = 'en-gb' WHERE store_id = 0 AND {setting_scope_column(runtime)} = 'config' AND `key` = 'config_admin_language' AND value = 'en'")
            if cursor.rowcount != 1:
                raise AssertionError("OpenCart's admin language setting could not be changed to en-gb.")

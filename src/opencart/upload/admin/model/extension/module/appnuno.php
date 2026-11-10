<?php
if (!class_exists('AppnunoConfig', false)) {
	if (defined('DIR_SYSTEM') && is_file(DIR_SYSTEM . 'library/appnuno-config.php')) {
		require_once DIR_SYSTEM . 'library/appnuno-config.php';
	} else {
		require_once dirname(__FILE__) . '/../../../../../../shared/php/appnuno-config.php';
	}
}

class ModelExtensionModuleAppnuno extends Model {
	public function defaultConfig() {
		return AppnunoConfig::defaultConfig();
	}

	public function getDestinations($language_id, $store_id) {
		$destinations = array(
			array(
				'label' => 'Home',
				'url' => '/'
			),
			array(
				'label' => 'Special offers',
				'url' => '/index.php?route=product/special'
			),
			array(
				'label' => 'Search',
				'url' => '/index.php?route=product/search'
			),
			array(
				'label' => 'Account',
				'url' => '/index.php?route=account/account'
			),
			array(
				'label' => 'Login',
				'url' => '/index.php?route=account/login'
			),
			array(
				'label' => 'Register',
				'url' => '/index.php?route=account/register'
			),
			array(
				'label' => 'Cart',
				'url' => '/index.php?route=checkout/cart'
			),
			array(
				'label' => 'Checkout',
				'url' => '/index.php?route=checkout/checkout'
			),
			array(
				'label' => 'Contact',
				'url' => '/index.php?route=information/contact'
			)
		);

		$information = $this->db->query("SELECT i.information_id, id.title FROM " . DB_PREFIX . "information i INNER JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) INNER JOIN " . DB_PREFIX . "information_to_store i2s ON (i.information_id = i2s.information_id) WHERE id.language_id = '" . (int)$language_id . "' AND i2s.store_id = '" . (int)$store_id . "' AND i.status = '1' ORDER BY id.title ASC");

		foreach ($information->rows as $page) {
			$destinations[] = array(
				'label' => 'Information: ' . $page['title'],
				'url' => '/index.php?route=information/information&information_id=' . (int)$page['information_id']
			);
		}

		$categories = $this->db->query("SELECT c.category_id, cd.name, (SELECT GROUP_CONCAT(cp.path_id ORDER BY cp.level SEPARATOR '_') FROM " . DB_PREFIX . "category_path cp WHERE cp.category_id = c.category_id) AS category_path FROM " . DB_PREFIX . "category c INNER JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) INNER JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE cd.language_id = '" . (int)$language_id . "' AND c2s.store_id = '" . (int)$store_id . "' AND c.status = '1' ORDER BY cd.name ASC");

		foreach ($categories->rows as $category) {
			$path = isset($category['category_path']) && strlen($category['category_path']) ? $category['category_path'] : (int)$category['category_id'];

			$destinations[] = array(
				'label' => 'Category: ' . $category['name'],
				'url' => '/index.php?route=product/category&path=' . $path
			);
		}

		return $destinations;
	}

	public function validateConfig($json, $language_codes = array()) {
		return AppnunoConfig::validateConfig($json, $language_codes);
	}

	public function normalizeConfig($json) {
		return AppnunoConfig::normalizeConfig($json);
	}
}

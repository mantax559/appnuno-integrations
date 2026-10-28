<?php
class ModelExtensionModuleAppnuno extends Model {
	public function getState() {
		$products = $this->cart->getProducts();
		$count = 0;

		foreach ($products as $product) {
			if (isset($product['quantity']) && is_numeric($product['quantity'])) {
				$count += (int)$product['quantity'];
			}
		}

		$identity = null;

		if ($this->customer->isLogged()) {
			$identifier = (string)$this->customer->getId();
			$this->session->data['appnuno_customer_id'] = $identifier;
			$identity = array(
				'identifier' => $identifier,
				'guard' => 'customer',
				'is_logged_in' => true
			);
		} elseif (isset($this->session->data['appnuno_customer_id']) && is_string($this->session->data['appnuno_customer_id']) && strlen($this->session->data['appnuno_customer_id'])) {
			$identity = array(
				'identifier' => $this->session->data['appnuno_customer_id'],
				'guard' => 'customer',
				'is_logged_in' => false
			);
		}

		return array(
			'cartCount' => $count,
			'identity' => $identity
		);
	}
}

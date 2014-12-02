<?php namespace rexlu\Laravelodbc;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;

class ODBCConnector extends Connector implements ConnectorInterface {

	/**
	 * Establish a database connection.
	 *
	 * @param  array  $options
	 * @return PDO
	 */
	public function connect(array $config)
	{
		$options = $this->getOptions($config);

        $dsn = array_get($config, 'dsn');

		$pdo = $this->createConnection($dsn, $config, $options);
		$pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
		return $pdo;

	}

	

}
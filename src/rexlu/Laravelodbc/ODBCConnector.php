<?php namespace rexlu\Laravelodbc;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use PDO;

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
		$pdo->setAttribute(PDO::ATTR_AUTOCOMMIT,TRUE);
		return $pdo;
	}

}
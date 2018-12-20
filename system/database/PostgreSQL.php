<?php

class PVPostgreSql {
	
	/**
	 * Execute a query.
	 * 
	 * @param string $query
	 */
	public function query(string $query) {

		$result = pg_query(self::$link, $query);

		return $result;
	}

	/**
	 * Returns the last interested id of a query.
	 * 
	 * @param string $query The query to be executed
	 * @param string $returnField The returning field
	 * @param string $returnTable Void in this case, not used
	 */
	public function return_last_insert_query(string $query, string $returnField = '', string $returnTable = '') {

		$result = pg_exec($query . " RETURNING $returnField ");
		$row = self::fetchArray($result);
		$id = $row[$returnField];

		return $id;

	}

	/**
	 * Gets the number of rows found in a result
	 * 
	 * @param resource $result The result
	 * 
	 * @return int
	 */
	public function resultRowCount(resource $result) {
		$count = pg_num_rows($result);

		return $count;
	}
	
	/**
	 * Fetches the the results of a row in the form of an array
	 * 
	 * @param resource $result A resource from an executed query
	 * 
	 * @return
	 */
	public function fetchArray(resource $result) {
		$array = pg_fetch_array($result);

		return $array;
	}

	public function fetchFields($result) {

		$fields = pg_fetch_assoc($result);

		return $fields;

	}

	public function makeSafe($string) {
		if (is_array($string)) {
			$return_array = array();

			foreach ($string as $key => $value) {
				$return_array[$key] = $this->makeSafe($value);
			}

		} else {

			$return_array = pg_escape_string(self::$link, $string);
		}

		return $return_array;
	}

	public function closeDB() {

		pg_close(self::$link);

	}

	public function getSchema($append_period = true) {

		if (empty(self::$dbschema)) {
			$schema = '';
		} else if ($append_period) {
			$schema = self::$dbschema . ".";
		} else {
			$schema = self::$dbschema;
		}

		return $schema;
	}

	public function clearTableData($tablename, $options = '') {
		$tablename = self::makeSafe($tablename);

		$query = "TRUNCATE TABLE $tablename $options";
		self::query($query);
	}

	public function tableExist($tablename, $schema = '') {
		$query = '';

		$query = "select * from information_schema.tables where table_name  = '$tablename' ";
		if (!empty($schema))
			$query .= "AND table_schema = '$schema'";

		$result = self::query($query);
		$count = self::resultRowCount($result);

		if ($count <= 0 && empty($count)) {
			return FALSE;
		}

		return TRUE;
	}

	public function columnExist($table_name, $field_name) {

		$query = "SELECT * FROM information_schema.columns WHERE table_schema = '" . self::getSchema(false) . "' AND table_name = '$table_name' AND column_name = '$field_name';";

		$result = self::query($query);
		$count = self::resultRowCount($result);
		self::_notify(get_class() . '::' . __FUNCTION__, $count, $result, $table_name, $field_name);

		if ($count <= 0) {
			return FALSE;
		}

		return TRUE;

	}

	public function getSQLRandomOperator() {
		$function = 'RANDOM()';

		return $function;
	}

	public function formatData($string) {

	}

	public function dbAverageFunction($field) {

		$function = ' AVG(' . $field . ') ';

		return $function;
	}

	public function getDatabaseType() {
		return 'postgresql';
	}

	public function getConnectionName() {

	}

	public function getPagininationOffset($table, $join_clause = '', $where_clause = '', $current_page = 0, $results_per_page = 20, $order_by = '', $fields = 'COUNT(*) as count') {
		if (!empty($where_clause) && !is_array($where_clause))
			$where_clause .= ' WHERE ' . $args['where'];
		else if (is_array($where_clause) && !empty($where_clause)) {
			$query = ' WHERE ';
			$first = true;
			foreach ($where_clause as $key => $value) {
				if (is_array($value))
					$query .= self::parseOperators($key, $value, 'AND', '=', $first);
				else {
					if ($first) {
						if (PVValidator::isInteger($key)) {
							$query .= ' ' . $value . ' ';
						} else {
							$query .= $key . ' = \'' . self::makeSafe($value) . '\'';
						}
					} else {
						if (PVValidator::isInteger($key)) {
							$query .= ' AND ' . $value . ' ';
						} else {
							$query .= ' AND ' . $key . ' = \'' . self::makeSafe($value) . '\'';
						}
					}
				}

				$first = false;
			}

			$where_clause = $query;
		}

		$query = "SELECT $fields FROM $table $join_clause $where_clause";

		$result = self::query($query);
		$total_pages = self::fetchArray($result);
		$total_pages = $total_pages['count'];
		$from_clause = '';

		//Get The Start Page
		if ($current_page) {
			$start_location = ($current_page - 1) * $results_per_page;
		} else {
			$start_location = 0;
		}

		$last_page = ceil($total_pages / $results_per_page);

		if ($current_page < 1) {
			$current_page = 1;
		} else if ($current_page > $last_page) {
			$current_page = $last_page;
		}

		$database_type = self::getDatabaseType();

		$limit_offset = ' LIMIT ' . ($current_page - 1) * $results_per_page . ' OFFSET ' . $results_per_page;

		$return_array = array(
			'limit_offset' => $limit_offset,
			'current_page' => $current_page,
			'last_page' => $last_page,
			'start_location' => $start_location,
			'total_pages' => $total_pages,
			'from_clause' => $from_clause
		);

		return $return_array;

	}

	public function getDatabaseLink() {
		return $this->_handler;
	}

	public function insertStatement($table_name, $data, $options = array()) {

		if (!empty($table_name)) {
			$first = 1;
			foreach ($data as $key => $value) {

				if ($first == 0) {
					$columns .= ' ,' . $key;
					$values .= ' ,\'' . self::makeSafe($value) . '\' ';
				} else {
					$columns = $key;
					$values = ' \'' . self::makeSafe($value) . '\' ';
				}

				$first = 0;
			}//end foreach

			$query = 'INSERT INTO ' . $table_name . '(' . $columns . ') VALUES(' . $values . ')';
			$result = self::query($query);
		}

		return $result;
	}

	public function updateStatement($table, $data, $wherelist, $options = array()) {

		$query = 'UPDATE ' . $table . ' SET ';
		$params = array();
		$params_holder = array();

		if (is_array($data)) {
			foreach ($data as $key => $value) {
				$query .= $key . ' = \'' . self::makeSafe($value) . '\'';
			}//end foreach

		} else {
			$query .= $data;
		}

		if (is_array($wherelist) && !empty($wherelist)) {
			$query .= ' WHERE ';
			$first = true;

			foreach ($wherelist as $key => $value) {
				if (is_array($value))
					$query .= self::parseOperators($key, $value, 'AND', '=', $first);
				else {
					if ($first)
						$query .= $key . ' = \'' . self::makeSafe($value) . '\'';
					else {
						$query .= ' AND ' . $key . ' = \'' . self::makeSafe($value) . '\'';
					}
				}

				$first = false;
			}//end foreach

		} else if (!empty($wherelist)) {
			$query .= ' WHERE ' . $wherelist;
		}

		$result = self::query($query);

		return $result;
	}

	public function selectStatement(array $args, array $options = array()) {

		$default = array(
			'fields' => '*',
			'where' => '',
			'into' => '',
			'from' => '',
			'join' => '',
			'group_by' => '',
			'having' => '',
			'order_by' => '',
			'limit' => '',
			'offset' => '',
			'paged' => false,
			'results_per_page' => 10,
			'current_page' => 0
		);

		$args += $default;

		$query = '';

		if (is_array($args['fields'])) {
			$fields = implode(',', $args['fields']);
		} else {
			$fields = $args['fields'];
		}

		$query .= 'SELECT ' . $fields . ' ';

		$query .= ' FROM ' . $args['table'] . ' ';

		if (is_array($args['join'])) {
			foreach ($args['join'] as $key => $value) {
				if (is_array($value)) {
					$type = isset($value['type']) ? $value['type'] : ' NATURAL JOIN ';
					$table = isset($value['table']) ? $value['table'] : $key;
					$on = isset($value['on']) ? $value['on'] : '';

					$query .= $type . ' ' . $table . ' ' . $on;
				} else {
					$query .= ' JOIN ' . $value;
				}
			}
		} else {
			$query .= ' ' . $args['join'];
		}

		if (!empty($args['where']) && !is_array($args['where']))
			$query .= ' WHERE ' . $args['where'];
		else if (is_array($args['where']) && !empty($args['where'])) {
			$query .= ' WHERE ';
			$first = true;
			foreach ($args['where'] as $key => $value) {
				if (is_array($value))
					$query .= self::parseOperators($key, $value, 'AND', '=', $first);
				else {
					if ($first)
						$query .= $key . ' = \'' . self::makeSafe($value) . '\'';
					else {
						$query .= ' AND ' . $key . ' = \'' . self::makeSafe($value) . '\'';
					}
				}

				$first = false;
			}
		}

		if (!empty($args['order_by']) && is_array($args['order_by'])) {
			$query .= ' ORDER BY ' . implode(',', $args['order_by']);
		} else if (!empty($args['order_by'])) {
			$query .= ' ORDER BY ' . $args['order_by'];
		}

		$result = PVDatabase::query($query);

		return $result;

	}

	public function deleteStatement(array $args, array $options = array()) {
		$default = array(
			'where' => '',
			'into' => '',
			'from' => '',
			'join' => '',
			'group_by' => '',
			'having' => '',
			'order_by' => '',
			'limit' => '',
			'offset' => '',
		);

		$args += $default;

		$query = '';

		$query .= 'DELETE FROM FROM ' . $args['table'] . ' ';

		if (is_array($args['join'])) {
			$query .= implode(',', $args['join']);
		} else {
			$query .= ' ' . $args['join'];
		}

		if (!empty($args['where']))
			$query .= ' WHERE ';
		if (is_array($args['where'])) {
			$first = true;
			foreach ($args['where'] as $key => $value) {
				if (is_array($value))
					$query .= self::parseOperators($key, $value, 'AND', '=', $first);
				else {
					if ($first)
						$query .= $key . ' = \'' . self::makeSafe($value) . '\'';
					else {
						$query .= ' AND ' . $key . ' = \'' . self::makeSafe($value) . '\'';
					}
				}

				$first = false;
			}
		} else {
			$query .= $args['where'];
		}

		$result = PVDatabase::query($query);

		return $result;
	}

	public function preparedQuery($query, $data, $formats = '') {
		$result = pg_prepare(self::$link, '', $query);
		$result = pg_execute(self::$link, '', $data);

		return $result;
	}

	public function preparedInsert($table_name, $data, $formats = array()) {

		$query = 'INSERT INTO ' . $table_name;
		$values = '';
		$placeholders = '';

		if (!empty($data)) {
			$values .= '(';
			$placeholders .= '(';
		}//end if

		$first = 1;
		$count = 0;
		foreach ($data as $key => $value) {
			if ($first) {
				$values .= $key;
				$placeholders .= ' ' . self::getPreparedPlaceHolder($count + 1) . ' ';
			} else {
				$values .= ' , ' . $key;
				$placeholders .= ', ' . self::getPreparedPlaceHolder($count + 1) . ' ';
			}

			$first = 0;
			$count++;
		}//end foreach

		if (!empty($data)) {
			$values .= ')';
			$placeholders .= ')';
		}//end if

		$query .= $values . ' VALUES' . $placeholders;

		$template_name = md5($query);

		$result = pg_query_params(self::$link, 'SELECT name FROM pg_prepared_statements WHERE name = $1', array($template_name));

		if (pg_num_rows($result) == 0) {
			$result = pg_prepare(self::$link, $template_name, $query);

		}

		$result = pg_execute(self::$link, $template_name, $data);

		return $result;

	}

	public function preparedReturnLastInsert($table_name, $returnField, $returnTable, $data, $formats = array(), $options = array()) {

		$query = 'INSERT INTO ' . $table_name;
		$values = '';
		$placeholders = ' VALUES';

		if (!empty($data)) {
			$values .= '(';
			$placeholders .= '(';
		}//end if

		$first = 1;
		$params = array();
		$count = 0;
		foreach ($data as $key => $value) {
			if ($first) {
				$values .= $key;
				$placeholders .= ' ' . self::getPreparedPlaceHolder($count + 1) . ' ';
			} else {
				$values .= ' , ' . $key;
				$placeholders .= ', ' . self::getPreparedPlaceHolder($count + 1) . ' ';
			}

			$params[$key] = (isset($formats[$count])) ? $formats[$count] : 's';
			$count++;
			$first = 0;
		}//end foreach

		if (!empty($data)) {
			$values .= ')';
			$placeholders .= ')';
		}//end if

		$query .= $values . $placeholders;

		$template_name = md5($query);

		$template_name = md5($query . " RETURNING $returnField");

		$result = pg_query_params(self::$link, 'SELECT name FROM pg_prepared_statements WHERE name = $1', array($template_name));

		if (pg_num_rows($result) == 0) {
			$result = pg_prepare(self::$link, $template_name, $query . " RETURNING $returnField");
		}

		$result = pg_execute(self::$link, $template_name, $data);

		if ($result == false) {
			self::catchDBError();
		}
		$row = self::fetchArray($result);

		$id = $row[$returnField];

		return $id;

	}

	public function preparedSelect($query, $data, array $formats = array(), array $options = array()) {

		$params = array();

		$count = 0;

		foreach ($data as $key => $value) {
			$params[$key] = (isset($formats[$count])) ? $formats[$count] : 's';
			$count++;
		}//end foreach

		if (isset($options['prequery']) && !empty($options['prequery'])) {
			$query = $options['prequery'] . $query;
		}

		if (isset($options['postquery']) && !empty($options['postquery'])) {
			$query = $query . $options['postquery'];
		}

		$result = pg_prepare(self::$link, '', $query);
		$result = pg_execute(self::$link, '', $data);

		return $result;

	}

	public function selectPreparedStatement(array $args, array $options = array()) {
		$default = array(
			'fields' => '*',
			'where' => '',
			'into' => '',
			'from' => '',
			'join' => '',
			'group_by' => '',
			'having' => '',
			'order_by' => '',
			'limit' => '',
			'offset' => '',
			'prequery' => '',
			'postquery' => '',
		);

		$args += $default;

		$query = '';

		if (is_array($args['fields'])) {
			$fields = implode(',', $args['fields']);
		} else {
			$fields = $args['fields'];
		}

		$query .= 'SELECT ' . $fields . ' ';

		$query .= ' FROM ' . $args['table'] . ' ';

		if (is_array($args['join'])) {
			foreach ($args['join'] as $key => $value) {
				if (is_array($value)) {
					$type = isset($value['type']) ? $value['type'] : ' NATURAL JOIN ';
					$table = isset($value['table']) ? $value['table'] : $key;

					$query .= $type . ' ' . $table;
				} else {
					$query .= ' JOIN ' . $value;
				}
			}
		} else {
			$query .= ' ' . $args['join'];
		}

		if (!empty($args['where']))
			$query .= ' WHERE ';
		if (is_array($args['where'])) {
			$first = true;
			foreach ($args['where'] as $key => $value) {
				if (is_array($value))
					$query .= self::parseOperators($key, $value, 'AND', '=', $first);
				else {
					if ($first) {
						if (PVValidator::isInteger($key)) {
							$query .= ' ' . $value . ' ';
						} else {
							$query .= $key . ' = \'' . self::makeSafe($value) . '\'';
						}
					} else {
						if (PVValidator::isInteger($key)) {
							$query .= ' AND ' . $value . ' ';
						} else {
							$query .= ' AND ' . $key . ' = \'' . self::makeSafe($value) . '\'';
						}
					}
				}

				$first = false;
			}
		} else {
			$query .= $args['where'];
		}

		if (!empty($args['group_by']) && is_array($args['group_by'])) {
			$query .= ' GROUP BY ' . implode(',', $args['group_by']);
		} else if (!empty($args['group_by'])) {
			$query .= ' GROUP BY ' . $args['group_by'];
		}

		if (!empty($args['order_by']) && is_array($args['order_by'])) {
			$query .= ' ORDER BY ' . implode(',', $args['order_by']);
		} else if (!empty($args['order_by'])) {
			$query .= ' ORDER BY ' . $args['order_by'];
		}

		if (!empty($args['limit'])) {
			$query .= ' LIMIT ' . $args['limit'];
		}

		if (isset($options['prequery']) && !empty($options['prequery'])) {
			$query = $options['prequery'] . $query;
		}

		if (!empty($args['offset'])) {
			$query .= ' OFFSET ' . $args['offset'];
		}

		if (isset($options['postquery']) && !empty($options['postquery'])) {
			$query = $query . $options['postquery'];
		}

		$result = PVDatabase::query($query);
	}

	public function preparedUpdate($table, $data, $wherelist, $formats = array(), $whereformats = array(), $options = array()) {
		$query = 'UPDATE ' . $table . ' SET ';
		$params = array();
		$params_holder = array();

		$first = 1;
		$count = 0;
		foreach ($data as $key => $value) {
			$params[] = (isset($formats[$count])) ? $formats[$count] : 's';
			$params_holder[] = $value;

			if ($first) {
				$query .= $key . '=' . self::getPreparedPlaceHolder($count + 1) . ' ';
			} else {
				$query .= ',' . $key . '=' . self::getPreparedPlaceHolder($count + 1) . ' ';
			}
			$count++;
			$first = 0;
		}//end foreach

		$first = 1;
		if (is_array($wherelist) && !empty($wherelist)) {
			$query .= ' WHERE ';
			$count2 = 0;
			foreach ($wherelist as $key => $value) {
				$params[] = (isset($wherelist[$count2])) ? $formats[$count2] : 's';
				$params_holder[] = $value;

				if ($first) {
					$query .= $key . '=' . self::getPreparedPlaceHolder($count + 1) . ' ';
				} else {
					$query .= ' AND ' . $key . '=' . self::getPreparedPlaceHolder($count + 1) . ' ';
				}
				$count++;
				$count2++;
				$first = 0;
			}//end foreach
		}//end if is_array and not emptys

		$template_name = md5($query);

		$result = pg_query_params(self::$link, $query, $params_holder);

		$result = pg_query_params(self::$link, 'SELECT name FROM pg_prepared_statements WHERE name = $1', array($template_name));

		if (pg_num_rows($result) == 0) {
			$result = pg_prepare(self::$link, $template_name, $query);
		}

		$result = pg_execute(self::$link, $template_name, $params_holder);

		return $result;
	}

	public function preparedDelete($table, $wherelist = array(), $whereformats = array(), $options = array()) {
		$query = 'DELETE FROM ' . $table;

		if (is_array($wherelist) && !empty($wherelist)) {
			$params = array();
			$query .= ' WHERE ';
			$count = 0;
			$first = 1;
			foreach ($wherelist as $key => $value) {
				$params[$key] = (isset($wherelist[$count])) ? $formats[$count] : 's';
				if ($first) {
					$query .= $key . '=' . self::getPreparedPlaceHolder($count + 1) . ' ';
				} else {
					$query .= ' AND ' . $key . '=' . self::getPreparedPlaceHolder($count + 1) . ' ';
				}

				$first = 0;
				$count++;
			}//end foreach
		}

		$template_name = md5($query);

		$result = pg_query_params(self::$link, 'SELECT name FROM pg_prepared_statements WHERE name = $1', array($template_name));

		if (pg_num_rows($result) == 0) {
			$result = pg_prepare(self::$link, $template_name, $query);
		}

		$result = pg_execute(self::$link, $template_name, $wherelist);

		return $result;
	}

	public function getPreparedPlaceHolder($count = 1) {

		$placeholder = '$' . $count;

		return $placeholder;
	}

	public function formatTableName($table_name, $append_schema = true, $append_prefix = true) {
		$table_name = self::$dbprefix . $table_name;

		if (!empty(self::$dbschema) && $append_schema) {
			$table_name = self::$dbschema . '.' . $table_name;
		}

		return $table_name;
	}

	protected function parseOperators($column, $args = array(), $key = 'AND', $operator = '=', $first = true) {

		$query = '';

		if (PVValidator::isInteger($key)) {
			$key = 'AND';
		}

		foreach ($args as $subkey => $arg) {

			if (($subkey == '>=' || $subkey == '>' || $subkey == '<' || $subkey == '<=' || $subkey == '!=') && !PVValidator::isInteger($subkey))
				$operator = $subkey;
			else if (!PVValidator::isInteger($subkey))
				$key = $subkey;

			if (is_array($arg)) {
				$query .= self::parseOperators($column, $arg, $key, $operator, $first);
			} else {

				if ($arg == 'IS NULL' || $arg == 'IS NOT NULL' || $arg == 'IS TRUE' || $arg == 'IS NOT TRUE' || $arg == 'IS FALSE' || $arg == 'IS NOT FALSE' || $arg == 'IS UNKNOWN' || $arg == 'IS NOT UNKNOWN') {
					$operator = '';

					if (!$first) {
						$query .= ' ' . $key . ' ' . $column . ' ' . $operator . ' ' . self::makeSafe($arg) . ' ';
					} else {

						$query .= ' ' . $column . ' ' . $operator . ' ' . self::makeSafe($arg) . ' ';
					}
				} else {

					if (!$first) {
						$query .= ' ' . $key . ' ' . $column . ' ' . $operator . ' \'' . self::makeSafe($arg) . '\' ';
					} else {

						$query .= ' ' . $column . ' ' . $operator . ' \'' . self::makeSafe($arg) . '\' ';
					}
				}

			}

			$first = false;
		}//end foreach

		return $query;

	}

	protected function bindParameters(&$statement, &$params) {

		$args = array();
		$args[] = implode('', array_values($params));
		foreach ($params as $paramName => $paramType) {
			$args[] = &$params[$paramName];
			$params[$paramName] = null;
		}

		call_user_func_array(array(
			&$statement,
			'bind_param'
		), $args);
	}

	protected function stmt_bind_assoc(&$stmt, &$out) {

		$data = mysqli_stmt_result_metadata($stmt);
		$fields = array();
		$out = array();

		$fields[0] = $stmt;
		$count = 1;

		while ($field = mysqli_fetch_field($data)) {
			$fields[$count] = &$out[$field->name];
			$count++;
		}
		@call_user_func_array(mysqli_stmt_bind_result, $fields);

	}

	public function createTable($table_name, $columns = array(), $options = array()) {

		$defaults = array(
			'format_table' => false,
			'execute' => true,
			'return_query' => true,
			'primary_key' => '',
		);
		$options += $defaults;

		$column_query = '';

		if (!empty($columns) && is_array($columns)) {
			$first = 1;
			foreach ($columns as $column_name => $column) {
				$column_query .= (!$first) ? ',' : '';
				$column_query .= self::formatColumn($column_name, $column);
				$first = 0;
			}
		}

		if (!empty($options['primary_key']))
			$column_query .= ',PRIMARY KEY(' . $options['primary_key'] . ')';

		if (!empty($column_query))
			$column_query = '(' . $column_query . ')';

		if ($options['format_table'])
			$table_name = self::formatTableName($table_name);

		$query = 'CREATE TABLE ' . $table_name . ' ' . $column_query . ';';

		if ($options['execute'])
			PVDatabase::query($query);

		if ($options['return_query'])
			return $query;
	}

	public function addColumn($table_name, $column_name, $column_data = array(), $options = array()) {

		$defaults = array(
			'format_table' => false,
			'execute' => true,
			'return_query' => true,
		);
		$options += $defaults;

		if ($options['format_table'])
			$table_name = self::formatTableName($table_name);

		$query = 'ALTER TABLE ' . $table_name . ' ADD COLUMN ' . self::formatColumn($column_name, $column_data) . ';';

		if ($options['execute'])
			PVDatabase::query($query);

		if ($options['return_query'])
			return $query;

	}

	public function formatColumn($name, $options = array()) {

		$defaults = array(
			'primary_key' => false,
			'unique' => false,
			'not_null' => true,
			'type' => 'string',
			'precision' => '',
			'default' => null,
			'auto_increment' => false,
			'execute_default' => false
		);

		$options += $defaults;

		$filtered = self::_applyFilter(get_class(), __FUNCTION__, array(
			'name' => $name,
			'options' => $options
		), array('event' => 'args'));
		$name = $filtered['name'];
		$options = $filtered['options'];

		$precision = (!empty($options['precision'])) ? '(' . $options['precision'] . ')' : '';
		$null = ($options['not_null'] == true) ? 'NOT NULL' : 'NULL';

		if (isset($options['default']) && is_callable($options['default'])) {
			$default = $options['default']();
		} else if ($options['execute_default']) {
			$default = (isset($options['default'])) ? 'DEFAULT ' . $options['default'] . '' : '';
		} else {
			$default = (isset($options['default'])) ? 'DEFAULT \'' . $options['default'] . '\'' : '';
		}
		$auto_increment = ($options['auto_increment'] == true) ? self::getAutoIncrement() : '';
		$unique = ($options['unique'] == true) ? 'UNIQUE' : '';

		if ($options['auto_increment'] == true) {
			$options['type'] = 'SERIAL';
		}

		$query = $name . ' ' . self::columnTypeMap($options['type']) . $precision . ' ' . $null . ' ' . $default . ' ' . $auto_increment . ' ' . $unique;

		return $query;
	}

	protected function getAutoIncrement() {
		return '';
	}

	protected function columnTypeMap($type) {
			
		$type = strtolower($type);

		$types = array(
			'integers' => array(
				'match' => array(
					'int',
					'integer',
					'numeric'
				),
				'database' => array(
					'mysql' => 'INT',
					'mssql' => 'INT',
					'postgresql' => 'INTEGER',
					'sqlite' => 'INTEGER'
				)
			),
			'bigintegers' => array(
				'match' => array('bigint'),
				'database' => array(
					'mysql' => 'BIGINT',
					'mssql' => 'BIGINT',
					'postgresql' => 'BIGINT',
					'sqlite' => 'INTEGER'
				)
			),
			'double' => array(
				'match' => array(
					'double',
					'float',
					'real'
				),
				'database' => array(
					'mysql' => 'DOUBLE',
					'mssql' => 'FLOAT',
					'postgresql' => 'DOUBLE PRECISION',
					'sqlite' => 'REAL'
				)
			),
			'string' => array(
				'match' => array(
					'string',
					'varchar',
					'character varying',
					'nchar',
					'native character',
					'nvarchar'
				),
				'database' => array(
					'mysql' => 'VARCHAR',
					'mssql' => 'VARCHAR',
					'postgresql' => 'CHARACTER VARYING',
					'sqlite' => 'TEXT'
				)
			),
			'text' => array(
				'match' => array(
					'text',
					'clob'
				),
				'database' => array(
					'mysql' => 'TEXT',
					'mssql' => 'TEXT',
					'postgresql' => 'TEXT',
					'sqlite' => 'TEXT'
				)
			),
			'blob' => array(
				'match' => array(
					'blob',
					'bytea'
				),
				'database' => array(
					'mysql' => 'BLOB',
					'mssql' => 'BLOB',
					'postgresql' => 'BYTEA',
					'sqlite' => 'TEXT'
				)
			),
			'boolean' => array(
				'match' => array('boolean'),
				'database' => array(
					'mysql' => 'BOOLEAN',
					'mssql' => 'BIT',
					'postgresql' => 'BOOLEAN',
					'sqlite' => 'INTEGER'
				)
			),
			'tinyint' => array(
				'match' => array(
					'tinyint',
					'smallint'
				),
				'database' => array(
					'mysql' => 'tinyint',
					'mssql' => 'tinyint',
					'postgresql' => 'smallint',
					'sqlite' => 'INTEGER'
				)
			),
			'timestamp' => array(
				'match' => array('timestamp'),
				'database' => array(
					'mysql' => 'TIMESTAMP',
					'mssql' => 'datetime',
					'postgresql' => 'TIMESTAMP',
					'sqlite' => 'TEXT'
				)
			),
			'date' => array(
				'match' => array(
					'date',
					'date/time',
					'datetime'
				),
				'database' => array(
					'mysql' => 'TIMESTAMP',
					'mssql' => 'datetime',
					'postgresql' => 'TIMESTAMP',
					'sqlite' => 'TEXT'
				)
			),
			'serial' => array(
				'match' => array('serial'),
				'database' => array(
					'mysql' => 'SERIAL',
					'mssql' => 'unknown',
					'postgresql' => 'serial',
					'sqlite' => 'INTEGER'
				)
			),
			'bigserial' => array(
				'match' => array('bigserial'),
				'database' => array(
					'mysql' => 'unknown',
					'mssql' => 'unknown',
					'postgresql' => 'bigserial',
					'sqlite' => 'INTEGER'
				)
			),
			'hstore' => array(
				'match' => array('hstore'),
				'database' => array(
					'mysql' => 'unknown',
					'mssql' => 'unknown',
					'postgresql' => 'hstore',
					'sqlite' => 'unknown'
				)
			),
			'uuid' => array(
				'match' => array(
					'uuid',
					'guid'
				),
				'database' => array(
					'mysql' => 'unknown',
					'mssql' => 'guid',
					'postgresql' => 'uuid',
					'sqlite' => 'TEXT'
				)
			),
			'ip' => array(
				'match' => array(
					'ip',
					'ipv4'
				),
				'database' => array(
					'mysql' => 'varchar',
					'mssql' => 'varchar',
					'postgresql' => 'cidr',
					'sqlite' => 'TEXT'
				)
			),
			'ipv6' => array(
				'match' => array('ipv6'),
				'database' => array(
					'mysql' => 'varchar',
					'mssql' => 'varchar',
					'postgresql' => 'inet',
					'sqlite' => 'TEXT'
				)
			),
			'json' => array(
				'match' => array('json'),
				'database' => array(
					'mysql' => 'TEXT',
					'mssql' => 'varchar',
					'postgresql' => 'json',
					'sqlite' => 'TEXT'
				)
			),
		);

		foreach ($types as $key => $value) {
			if (in_array($type, $value['match'])) {
				$match = $value['database'][self::$dbtype];
				$match = self::_applyFilter(get_class(), __FUNCTION__, $match, array('event' => 'return'));
				return $match;
			}//end if
		}//end for each

	}

	public function dropColumn($table_name, $column_name, $options = array()) {

		$defaults = array(
			'format_table' => false,
			'execute' => true,
			'return_query' => true,
		);
		
		$options += $defaults;

		if ($options['format_table'])
			$table_name = self::formatTableName($table_name);

		$query = 'ALTER TABLE ' . $table_name . ' DROP COLUMN ' . $column_name . ';';

		if ($options['execute'])
			PVDatabase::query($query);

		if ($options['return_query'])
			return $query;
	}

	public function dropTable($table_name, $options = array()) {

		$defaults = array(
			'format_table' => false,
			'execute' => true,
			'return_query' => true,
		);
		$options += $defaults;

		if ($options['format_table'])
			$table_name = self::formatTableName($table_name);

		$query = 'DROP TABLE ' . $table_name . ';';

		if ($options['execute'])
			PVDatabase::query($query);

		if ($options['return_query'])
			return $query;

	}

	public function catchDBError() {

	}

}

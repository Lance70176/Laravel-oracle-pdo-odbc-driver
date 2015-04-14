<?php namespace rexlu\Laravelodbc;

use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Builder;

class ODBCQueryGrammar extends Grammar {

	/**
	 * The keyword identifier wrapper format.
	 *
	 * @var string
	 */
	protected $wrapper = '%s';

    /**
	 * Compile the lock into SQL.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  bool|string  $value
	 * @return string
	 */
    protected function compileLock(Builder $query, $value)
    {
    	if (is_string($value)) return $value;
    	return $value ? 'for update' : 'lock in share mode';
    }

	/**
	 * Compile a select query into SQL.
	 *
	 * @param  \Illuminate\Database\Query\Builder
	 * @return string
	 */
	public function compileSelect(Builder $query)
	{
		if (is_null($query->columns)) $query->columns = array('*');
		$components = $this->compileComponents($query);
		// If an offset is present on the query, we will need to wrap the query in
		// a big "ANSI" offset syntax block. This is very nasty compared to the
		// other database systems but is necessary for implementing features.
		if ($query->limit > 0 OR $query->offset > 0)
		{
			return $this->compileAnsiOffset($query, $components);
		}

		return trim($this->concatenate($components));
	}

	/**
	 * Create a full ANSI offset clause for the query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $components
	 * @return string
	 */
	protected function compileAnsiOffset(Builder $query, $components)
	{
		$constraint = $this->compileRowConstraint($query);
		$sql = $this->concatenate($components);
		// We are now ready to build the final SQL query so we'll create a common table
		// expression from the query and get the records with row numbers within our
		// given limit and offset value that we just put on as a query constraint.
		$temp = $this->compileTableExpression($sql, $constraint, $query);
		return $temp;
	}

	/**
	 * Compile the limit / offset row constraint for a query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @return string
	 */
	protected function compileRowConstraint($query)
	{
		$start = $query->offset + 1;
		if ($query->limit > 0)
		{
			$finish = $query->offset + $query->limit;
			return "between {$start} and {$finish}";
		}
		return ">= {$start}";
	}

	/**
	 * Compile a common table expression for a query.
	 *
	 * @param  string  $sql
	 * @param  string  $constraint
	 * @param Builder $query
	 * @return string
 	 */
	protected function compileTableExpression($sql, $constraint, $query)
	{
		if ($query->limit > 0)
		{
			return "select t2.* from ( select rownum AS \"rn\", t1.* from ({$sql}) t1 ) t2 where t2.\"rn\" {$constraint}";
		}
		else
		{
			return "select * from ({$sql}) where rownum {$constraint}";
		}
	}

	/**
	 * Compile the "limit" portions of the query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  int  $limit
	 * @return string
	 */
	protected function compileLimit(Builder $query, $limit)
	{
		return '';
	}

	/**
	 * Compile the "offset" portions of the query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  int  $offset
	 * @return string
	 */
	protected function compileOffset(Builder $query, $offset)
	{
		return '';
	}

	/**
	* Compile a truncate table statement into SQL.
	*
	* @param  \Illuminate\Database\Query\Builder  $query
	* @return array
	*/
	public function compileTruncate(Builder $query)
	{
		return array('truncate table '.$this->wrapTable($query->from) => array());
	}

	/**
	 * Compile an insert statement into SQL.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $values
	 * @return string
	 */
	public function compileInsert(Builder $query, array $values)
	{
		// Essentially we will force every insert to be treated as a batch insert which
		// simply makes creating the SQL easier for us since we can utilize the same
		// basic routine regardless of an amount of records given to us to insert.
		$table = $this->wrapTable($query->from);
		if ( ! is_array(reset($values)))
		{
			$values = array($values);
		}
		$columns = $this->columnize(array_keys(reset($values)));
		// We need to build a list of parameter place-holders of values that are bound
		// to the query. Each insert should have the exact same amount of parameter
		// bindings so we can just go off the first list of values in this array.
		$parameters = $this->parameterize(reset($values));
		$value = array_fill(0, count($values), "($parameters)");
		if (count($value) > 1)
		{
			$insertQueries = array();
			foreach ($value as $parameter)
			{
				$parameter = (str_replace(array('(',')'), '', $parameter));
				$insertQueries[] = "select ". $parameter . " from dual ";
			}
			$parameters = implode('union all ', $insertQueries);
			return "insert into $table ($columns) $parameters";
		}
		$parameters = implode(', ', $value);
		return "insert into $table ($columns) values $parameters";
	}

	/**
	  * Compile an insert and get ID statement into SQL.
	  *
	  * @param  \Illuminate\Database\Query\Builder  $query
	  * @param  array   $values
	  * @param  string  $sequence
	  * @return string
	  */
	public function compileInsertGetId(Builder $query, $values, $sequence)
	{
		if (is_null($sequence)) $sequence = 'id';
		return $this->compileInsert($query, $values);
	}

	/**
	  * Compile an insert with blob field statement into SQL.
	  *
	  * @param  \Illuminate\Database\Query\Builder  $query
	  * @param  array   $values
	  * @param  array   $binaries
	  * @param  string   $sequence
	  * @return string
	  */
	public function compileInsertLob(Builder $query, $values, $binaries, $sequence = null)
	{
		if (is_null($sequence)) $sequence = 'id';
		$table = $this->wrapTable($query->from);
		if ( ! is_array(reset($values)))
		{
			$values = array($values);
		}
		if ( ! is_array(reset($binaries)))
		{
			$binaries = array($binaries);
		}
		$columns = $this->columnize(array_keys(reset($values)));
		$binaryColumns = $this->columnize(array_keys(reset($binaries)));
		$columns .= ', ' . $binaryColumns;
		$parameters = $this->parameterize(reset($values));
		$binaryParameters = $this->parameterize(reset($binaries));
		$value = array_fill(0, count($values), "$parameters");
		$binaryValue = array_fill(0, count($binaries), str_replace('?', 'EMPTY_BLOB()',$binaryParameters));
		$value = array_merge($value, $binaryValue);
		$parameters = implode(', ', $value);
		return "insert into $table ($columns) values ($parameters)";
	}

	/**
	 * Compile an update statement into SQL.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $values
	 * @param  array  $binaries
	 * @param  string  $sequence
	 * @return string
	 */
	public function compileUpdateLob(Builder $query, array $values, array $binaries, $sequence = null)
	{
		if (is_null($sequence)) $sequence = 'id';
		$table = $this->wrapTable($query->from);
		// Each one of the columns in the update statements needs to be wrapped in the
		// keyword identifiers, also a place-holder needs to be created for each of
		// the values in the list of bindings so we can make the sets statements.
		$columns = array();
		foreach ($values as $key => $value)
		{
			$columns[] = $this->wrap($key).' = '.$this->parameter($value);
		}
		$columns = implode(', ', $columns);
		// set blob variables
		if ( ! is_array(reset($binaries)))
		{
			$binaries = array($binaries);
		}
		$binaryColumns = $this->columnize(array_keys(reset($binaries)));
		$binaryParameters = $this->parameterize(reset($binaries));
		// create EMPTY_BLOB sql for each binary
		$binarySql = array();
		foreach ( (array) $binaryColumns as $binary)
		{
			$binarySql[] = "$binary = EMPTY_BLOB()";
		}
		// prepare binary SQLs
		if (count($binarySql))
		{
			$binarySql = ', ' . implode(',', $binarySql);
		}
		// If the query has any "join" clauses, we will setup the joins on the builder
		// and compile them so we can attach them to this update, as update queries
		// can get join statements to attach to other tables when they're needed.
		if (isset($query->joins))
		{
			$joins = ' '.$this->compileJoins($query, $query->joins);
		}
		else
		{
			$joins = '';
		}
		// Of course, update queries may also be constrained by where clauses so we'll
		// need to compile the where clauses and attach it to the query so only the
		// intended records are updated by the SQL statements we generate to run.
		$where = $this->compileWheres($query);
		return "update {$table}{$joins} set $columns$binarySql $where";
	}

	/**
	 * Wrap a single string in keyword identifiers.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function wrapValue($value)
	{
		return $value !== '*' ? sprintf($this->wrapper, $value) : $value;
	}

    /**
     * Compile a basic where clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereBasic(Builder $query, $where)
    {
            $value = $this->parameter($where['value']);

            //Some bindValue by "?" for Oracle may have some problem
            $value = str_replace(".","_",":".$where['column']);
            $str_leng = strlen($value);

            if ($str_leng >= 30) {
            	$value = ':1';
            }

            return $this->wrap($where['column']).' '.$where['operator'].' '.$value;
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param  \Illuminate\Database\Query\Builder
     * @return array
     */
    protected function compileComponents(Builder $query)
    {
            $sql = array();

            foreach ($this->selectComponents as $component)
            {

                    // To compile the query, we'll spin through each component of the query and
                    // see if that component exists. If it does we'll just call the compiler
                    // function for the component which is responsible for making the SQL.
                    if ( ! is_null($query->$component))
                    {
                            $method = 'compile'.ucfirst($component);

                            //For Oracle, replace "
                            $sql[$component] = str_replace('"',"",$this->$method($query, $query->$component));
                    }
            }

            return $sql;
    }
}

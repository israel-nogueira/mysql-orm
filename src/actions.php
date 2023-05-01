<?
    namespace IsraelNogueira\MysqlOrm;
    use PDO;
    use Exception;
    use PDOException;
    use RuntimeException;
    use InvalidArgumentException;
    
    trait actions{

        public function transaction($return='none'){
            $this->transactionFn = true;
            $this->rollbackFn = $return;
        }

        public function rollbackExec($return){
            if($this->rollbackFn!='none'){
                call_user_func_array($this->rollbackFn,array($return));
            }
        }
    
        public function execQuery($P_ALIAS='response'){
            if($this->transactionFn == true){
                if($this->query==""){ return [];}
                $_QUERY = (!is_array($this->query))? [$this->query] : $this->query;
					try {
						$this->connection->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
						$this->connection->beginTransaction();
						foreach ($_QUERY as $_ALIAS => $query) {					
							$this->stmt = $this->connection->query($query);
							if (!$this->stmt) {
								throw new Exception($this->connection->errorInfo()[2]);
							}
							$this->startProcessResult($this->stmt,$query,$_ALIAS);
						}
						$this->connection->commit();
					} catch (PDOException $exception) {					
						$this->connection->rollback();
						if ($this->rollbackFn != false) {
							$this->rollbackExec($exception->getMessage());
						} else {
							throw new RuntimeException($exception);
						}
					}
            }
        }


		public function createSP($name,$params,$sql){
			return 'DELIMITER'.PHP_EOL.
					'//'.PHP_EOL.
					'	DROP PROCEDURE IF EXISTS '.$name.PHP_EOL.
					'//'.PHP_EOL.
					'	CREATE PROCEDURE '.$name.'('.implode(',',$params).')'.PHP_EOL.
					'	BEGIN '.PHP_EOL.$sql.PHP_EOL.
					'	END //'.PHP_EOL.
					'	DELIMITER ;';
		}


		public function create_view($name = null){
			if ($name == null) {
				throw new InvalidArgumentException('create_view(NULL), Error:' . __LINE__);
			}
			$this->view_query = 'CREATE OR REPLACE ALGORITHM=TEMPTABLE SQL SECURITY DEFINER VIEW ' . $name . ' AS (' . $this->get_query() . ')';
			try {
				if ($this->debug == true) {
					$this->stmt = $this->connection->prepare($this->view_query);
					$this->stmt->execute();
					if ($this->stmt->errorCode() != "00000") {
						$errorInfo = $this->stmt->errorInfo();
						throw new RuntimeException($errorInfo[2]);
					}
				} else {
					$this->stmt = $this->connection->prepare($this->view_query);
					$this->stmt->execute();
				}
			} catch (PDOException $exception) {
				if ($this->rollbackFn != false) {
					$this->rollbackExec($exception->getMessage());
				} else {
					throw new RuntimeException($exception);
				}
			}
			return $this;
		}

		public function insert_or_replace($type=null){
			$keyvalue = array();
			foreach ($this->InsertVars as $key => $value) {
				if ($value=='NULL') {
						$keyvalue[] = $key . 'NULL';
					}elseif(is_string($value)) {

						if (substr($value, 0, 8) == "command:") {$value = substr($value,8);} 
						$_verify = $this->functionVerify($value);
						if($_verify!==false){
							$keyvalue[] = $key.'='.$_verify['function'].'('.$_verify['params'].')';
						}else{
							$keyvalue[] = $key.'="'.$this->preventMySQLInject($value,$type).'"';
						}

				} else {
					$keyvalue[] = $key . "=" . $this->preventMySQLInject($value,$type);
				}
			}
			$this->on_duplicate = ' ON DUPLICATE KEY UPDATE ' . implode(',', $keyvalue);
			return $this;
		}

		public function prepare_insert($type=null){
			$queryPrepare = 'INSERT ';
			if (!empty($this->ignore)) {
				$queryPrepare .= $this->ignore;
			}
			$queryPrepare .= ' INTO ';
			if ($this->tableClass != null) {$queryPrepare .= $this->tableClass;} 
			if (count($this->InsertVars) > 0) {
				$keyvalue = array();
				foreach ($this->InsertVars as $key => $value) {
					if ($value=='NULL') {
						$keyvalue[] = 'NULL';
					}elseif (is_string($value)) {
						if (substr($value, 0, 8) == "command:") {$value = substr($value,8);} 
						$_verify = $this->functionVerify($value);
						if($_verify!==false){
							$keyvalue[] = $_verify['function'].'('.$_verify['params'].')';
						}else{
							$keyvalue[] = '"'.$this->preventMySQLInject($value,$type).'"';
						}
					} else {
						$keyvalue[] 		= $this->preventMySQLInject($value,$type);
					}
				}
				$queryPrepare .= ' ( ' . implode(',', array_keys($this->InsertVars)) . ' ) ';
			} 
			$queryPrepare .= ' VALUES ';
			if (count($this->InsertVars) > 0) {
				$queryPrepare .= ' (' . implode(',', $keyvalue) . ') ';
			} else {
				exit;
			};
			if ($this->set_where_not_exist == true) {$not = " NOT EXISTS ";
			} else { $not = "";}
			if (!empty($this->where)) {
				$queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')';
			}
			if (!empty($this->on_duplicate)) {
				$queryPrepare .= $this->on_duplicate;
			}
			if (is_null($this->query)) {
				$this->query = $queryPrepare;
			} else {
				if(!is_array($this->query)){$this->query = [];}
				$this->query[]		= $queryPrepare;
				$this->InsertVars	= [];
			}
			$this->clear();
		}

		public function insert(){
			$this->query= null;
			$this->prepare_insert();
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function prepare_replace($type=null){
			$queryPrepare = 'REPLACE ';
			if (!empty($this->ignore)) {
				$queryPrepare .= $this->ignore;
			}
			$queryPrepare .= ' INTO ';
			if ($this->tableClass != null) {$queryPrepare .= $this->tableClass;} 
			if (count($this->InsertVars) > 0) {
				$keyvalue = array();
				foreach ($this->InsertVars as $key => $value) {
					if ($value=='NULL') {
						$keyvalue[] = 'NULL';
					}elseif (is_string($value)) {
						if (substr($value, 0, 8) == "command:") {$value = substr($value,8);} 
						$_verify = $this->functionVerify($value);
						if($_verify!==false){
							$keyvalue[] = $_verify['function'].'('.$_verify['params'].')';
						}else{
							$keyvalue[] = '"'.$this->preventMySQLInject($value,$type).'"';
						}
					} else {
						$keyvalue[] 		= $this->preventMySQLInject($value,$type);
					}
				}
				$queryPrepare .= ' ( ' . implode(',', array_keys($this->InsertVars)) . ' ) ';
			} 
			$queryPrepare .= ' VALUES ';
			if (count($this->InsertVars) > 0) {
				$queryPrepare .= ' (' . implode(',', $keyvalue) . ') ';
			} else {
				exit;
			};
			if ($this->set_where_not_exist == true) {$not = " NOT EXISTS ";
			} else { $not = "";}
			if (!empty($this->where)) {
				$queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')';
			}
			if (!empty($this->on_duplicate)) {
				$queryPrepare .= $this->on_duplicate;
			}
			if (is_null($this->query)) {
				$this->query = $queryPrepare;
			} else {
				if(!is_array($this->query)){$this->query = [];}
				$this->query[]		= $queryPrepare;
				$this->InsertVars	= [];
			}
		}

		public function replace(){
			$this->query= null;
			$this->prepare_replace();
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function prepare_update($ALIAS='response'){
			$queryPrepare = 'UPDATE ';
			if ($this->tableClass != null) {
				$queryPrepare .= $this->tableClass;
			} else {
				throw new Exception('$this->tableClass UNDEFINED, linha:'.__LINE__,1);
			}
			$queryPrepare .= ' SET ';
			if (count($this->Insert_Update) > 0) {
				$queryPrepare .= implode(',', $this->Insert_Update);
			}else{
				return true;
			};

			if ($this->set_where_not_exist == true) {$not = " NOT EXISTS ";
			} else { $not = "";}

			if (!empty($this->where)) {
				$queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')';
			}
			if(!is_array($this->query)){$this->query = [];}
			$this->query[$ALIAS] = $queryPrepare;
			$this->clear();
		}

		public function update(){
			$this->query = null;
			$this->prepare_update();
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function prepare_delete(){
			$queryPrepare = 'DELETE FROM ';
			if (!empty($this->tableClass)) {
				$queryPrepare .= $this->tableClass;
			} 
			if ($this->set_where_not_exist == true) {
				$not = " NOT EXISTS ";
			} else { $not = "";}

			if (!empty($this->where)) {
				$queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')';
			}
			if (is_null($this->query)) {
				$this->query = $queryPrepare;
			} else {
				if(!is_array($this->query)){$this->query = [];}
				$this->query[] = $queryPrepare;
				$this->clear();
			}
		}

		public function delete(){
			$this->query = null;
			$this->prepare_delete();
			$this->execQuery();
			$this->clear();
			return $this;
		}


		public function responseSP($variable = null){
			if ($variable != null) {
				return $this->sp_response[$variable];
			} else {
				return $this->sp_response;
			}
		}

		public function execSP($_ALIAS='RESPONSE', $_RETORNO=null){				
				$this->prepare_execSP($_ALIAS,$_RETORNO);
				$this->execQuery();
				$this->clear();
				return $this;	
		}

		public function prepare_execSP($_ALIAS='RESPONSE', $_RETORNO=null){
			$this->connect();
			#---------------------------------------------------------------
			# TRATAMOS AS ENTRADAS
			#---------------------------------------------------------------
			foreach ($this->SP_PARAMS as $key=>$value) {

				if(is_array($value) || is_object($value)){

					$this->SP_PARAMS[$key] = "'".trim(json_encode($value,JSON_BIGINT_AS_STRING), '[]')."'";

				}elseif(is_numeric($value) || is_int($value) || is_float($value)){

					$this->SP_PARAMS[$key] = trim($value);

				}elseif(is_string($value)){

					$this->SP_PARAMS[$key] = "'".trim($value)."'";

				}elseif($value==null){

					$this->SP_PARAMS[$key] = 'NULL';
				}
			}
			if (!is_array($this->query)) {$this->query = [];}

			if($_RETORNO==null){
				$this->query[$_ALIAS] = 'CALL ' . $this->SP . '(' . implode(',',$this->SP_PARAMS). ');';
			}else{
				$this->query[$_ALIAS] = 'CALL ' . $this->SP . '(' . implode(',',$this->SP_PARAMS). ', @' . $_RETORNO . ');';
			}
		}

		public function prepare_select($alias = null, $script = null){
			if (!is_array($this->query)) {$this->query = [];}
			$this->query[$alias] = (is_null($script)) ? $this->get_query():$script;
			$this->clear();
			return $this;
		}

		public function select($alias = null, $script = null){	
			$this->prepare_select($alias, $script);
			$this->execQuery();
			$this->clear();
			return $this;
		}



    }
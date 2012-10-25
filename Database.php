<?php
/*            Database() v2/r03 is an simple, open-source MySQL wrapper          *
 *             (C) 2012 - Adrián Navarro <adrian.navarro#edu.uah.es>             *
 *                                                                               *
 *      This program is free software: you can redistribute it and/or modify     *
 *      it under the terms of the GNU General Public License as published by     *
 *      the Free Software Foundation, either version 3 of the License, or        *
 *      (at your option) any later version.                                      *
 *                                                                               *
 *      This program is distributed in the hope that it will be useful,          *
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of           *
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            *
 *      GNU General Public License for more details.                             *
 *                                                                               *
 *      You should have received a copy of the GNU General Public License        *
 *      along with this program.  If not, see <http://www.gnu.org/licenses/>.    */

class Database {

    public $hostname = null;
    public $username = null;
    public $password = null;
    public $database = null;
    
    public $debug = true;
    public $strict = false;
    
    public $affected_rows = null;
    public $insert_id = null;
    public $num_rows = null;
    
    private $connected = false;
    private $resource = null;
    
    public function __construct($hostname = null, $username = null, $password = null, $database = null) {
        // Saves parameters, will only connect if needed
        
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
    }

    public function connect() {
        // Connect -- called at @query if not connected

        if($this->connected) {
            return $this->debug('notice', 'Already connected');
        } elseif($this->username and $this->database) {
            if($res = @mysqli_connect($this->hostname, $this->username, $this->password, $this->database)) {
                $this->resource = $res;
                $this->connected = true;
                
                return $this->connected;
            } else {
                return $this->debug('fatal', "Could not connect to database {$this->database}@{$this->hostname}: ".mysqli_connect_error());
            }

        } else {
            return $this->debug('fatal', 'Wrong connection parameters.');
        }
    }
    
    public function close() {
        // Close connection and reverse state

        $this->connected = false;

        if($this->connected) {
               if(@mysqli_close($this->resource)) {
                   return true;
               } else {
                   return $this->debug('notice', 'Initial connection successful but connection gone before closing.');
               }
        } else {
            return $this->debug('notice', 'Not connected to any database.');
        }
    }
    
    public function escape($string) {
        // Escape string, using live server config if available

        if($this->connected) {
            return mysqli_real_escape_string($this->resource, $string);
        } else {
            return mysql_escape_string($string);
        }
    }
    
    private function debug($type = 'notice', $message) {
        // Produces debug messages and a return.

        if($this->debug) {
            switch($type) {
                case 'fatal':
                    echo '<h1>MySQL interface: <em>error</em></h1>';
                    echo $message;

                    return false;
                break;
                
                case 'notice':
                    echo '<h1>MySQL interface: <em>notice</em></h1>';
                    echo $message;

                    return false;
                break;
                
                default:
                    echo '<h1>MySQL interface: <em>other</em></h1>';
                    echo $message;

                    return false;
                break;
            }
        } elseif($this->strict) {
            switch($type) {
                case 'fatal':
                    exit('Database.php - Fatal Error');
                break;
                
                default:
                    return false;
                break;
            }
        } else {
            return false;
        }
    }
    
    public function query($query, $smart = false) {
        // Run a query. Distinguishes between SELECT, INSERT, UPDATE and others.
        // Extended queries not distinguished

        if(!$this->connected) $this->connect();

        if(!$this->connected) return false; // Still not connected after first attempt?

        $type = trim(strtolower(substr($query, 0, 7)));

        switch($type) {
            case 'select':
                $query_res = @mysqli_query($this->resource, $query);

                if($query_res) {
                    $this->num_rows = mysqli_num_rows($query_res); // Number of rows retrieved

                    return $query_res;
                } else {
                    return $this->debug('notice', 'Error sending query ('.$query.'). MySQL said: '.mysqli_error($this->resource));
                }
            break;
            
            case 'update':
                $query_res = @mysqli_query($this->resource, $query);

                if($query_res) {
                    $this->affected_rows = mysqli_affected_rows($this->resource); // Number of affected rows
                    
                    if($smart and $query_res) {
                        return ($this->affected_rows?$this->affected_rows:true);
                    } else {
                        return $query_res;
                    }
                } else {
                    return $this->debug('notice', 'Error sending query ('.$query.'). MySQL said: '.mysqli_error($this->resource));
                }
            break;
            
            case 'insert':
                $query_res = @mysqli_query($this->resource, $query);

                if($query_res) {
                    $this->insert_id = mysqli_insert_id($this->resource); // Generated ID number for auto_increment primary
                    
                    if($smart and $query_res) {
                        return ($this->insert_id?$this->insert_id:true);
                    } else {
                        return $query_res;
                    }
                } else {
                    $this->debug('notice', 'Error sending query ('.$query.'). MySQL said: '.mysqli_error($this->resource));
                }
            break;
            
            default:
                $query_res = @mysqli_query($this->resource, $query);

                if($query_res) {
                    return $query_res;
                } else {
                    return $this->debug('notice', 'Error running query ('.$query.'). MySQL said: '.mysqli_error($this->resource));
                }
            break;
        }
    }
    
    public function fetch_one($query, $table = false, $where = false, $limit = false) {
        // Returns the first element for the first row

        if($query and $table) {
            $query = $this->build_query($query, $table, $where, $limit);
        }

        $query = $this->query($query);

        if($query) {
            $buffer = mysqli_fetch_row($query);

            mysqli_free_result($query);

            return $buffer[0]; // Return the first element for the first row
        } else {
            return false;
        }
    }
    
    public function fetch_row($query, $table = false, $where = false, $limit = false) {
        // Returns the whole row as an object

        if($query and $table) {
            $query = $this->build_query($query, $table, $where, $limit);
        }

        $query = $this->query($query);

        if($query) {
            $buffer = mysqli_fetch_object($query);

            mysqli_free_result($query);

            return $buffer;
        } else {
            return false;
        }
    }
    
    public function fetch($query, $table = false, $where = false, $limit = false) {
        // Returns an array of objects

        if($query and $table) {
            $query = $this->build_query($query, $table, $where, $limit);
        }

        $query = $this->query($query);

        if($query) {
            $buffer = array();

            while($row = mysqli_fetch_object($query)) {
                $buffer[] = $row;
            }

            mysqli_free_result($query);

            return $buffer;
        } else {
            return false;
        }
    }
    
    public function update($table, $values, $where = false) {
        // Update a table (helper)

        if(is_string($table) and !empty($table) and is_array($values)) {
            $query = 'UPDATE `'.$this->escape($table).'` SET ';
            
            $stack_added = false;
            
            foreach($values as $value_key => $value) {
                if($value_key) {
                    $query .= '`';
                    $query .= $this->escape($value_key);
                    $query .= '`=';

                    if(is_integer($value)) {
                        $query .= (int)$this->escape($value);
                    } else {
                        $query .= '\''.$this->escape($value).'\'';
                    }

                    $query .= ',';

                    $stack_added = true;
                }
            }

            if($stack_added) {
                $query = trim(substr(trim($query), 0, -1));

                if($where and !is_array($where)) $where = array($where);

                if($where) {
                    $query .= ' WHERE ';
                    $where_query = '';

                    foreach($where as $where_key => $where_value) {
                        $or = false;
                        $and = false;

                        if(preg_match('/^OR /is', $where_key)) {
                            $or = true;
                            $and = false;

                            list(,$where_key) = explode(' ', $where_key, 2);
                        } elseif(preg_match('/^AND /is', $where_key)) {
                            $or = false;
                            $and = true;

                            list(,$where_key) = explode(' ', $where_key, 2);
                        } else {
                            $or = false;
                            $and = true;
                        }

                        if(!$where_key) $where_key = 'id';

                        if($or) {
                            $where_query .= ' OR ';
                        } elseif($and) {
                            $where_query .= ' AND ';
                        }
                        
                        $where_query .= '`'.$this->escape($where_key).'`=';

                        if(is_integer($where_value)) {
                            $where_query .= (int)$this->escape($where_value);
                        } else {
                            $where_query .= '\''.$this->escape($where_value).'\'';
                        }
                    }

                    if($or) {
                        $where_query = trim(substr(trim($where_query), 3));
                    } elseif($and) {
                        $where_query = trim(substr(trim($where_query), 4));
                    }

                    $query = $query . $where_query;
                } elseif($where) {
                    $query .= ' WHERE `id`=';
                    $query .= intval($where);
                }

                return $this->query($query, true);
            } else {
                return $this->debug('notice', 'Malformed update.');
            }
        } else {
            return $this->debug('fatal', 'Invalid update data.');
        }
    }
    
    public function insert($table, $values) {
        // Insert a new value (helper)

        if(is_string($table) and !empty($table) and is_array($values)) {
            $query = 'INSERT INTO `';
            $query .= $table;
            $query .= '` SET ';

            $stack_added = false;

            foreach($values as $value_key => $value) {
                if(!$value_key) continue;

                $query .= '`';
                $query .= $this->escape($value_key);
                $query .= '`=';

                if(is_integer($value)) {
                    $query .= (int)$this->escape($value);
                } else {
                    $query .= '\''.$this->escape($value).'\'';
                }

                $query .= ', ';
                $stack_added = true;
            }

            if($stack_added) {
                $query = trim(substr($query, 0, -2));
                
                return $this->query($query, true);
            } else {
                return $this->debug('notice', 'Malformed insertion.');
            }
        } else {
            return $this->debug('fatal', 'Invalid insertion data.');
        }
    }

    public function add($table) {
        // ActiveRecord helper

        return new DatabaseCollector($this, $table, 'insert');
    }
    
    public function modify($table) {
        // ActiveRecord helper

        return new DatabaseCollector($this, $table, 'update');
    }
    
    private function build_query($fields, $table, $where = false, $limit = false) {
        // Build query / internal helper

        if($fields == 'all') $fields = '*';

        $query = 'SELECT ';
        $query .= $fields;
        $query .= ' FROM ';
        $query .= '`'.$table.'`';

        if($where and !is_array($where)) $where = array($where);

        if($where) {
            $query .= ' WHERE ';
            $where_query = '';

            foreach($where as $where_key => $where_value) {
                $or = false;
                $and = false;

                if(preg_match('/^OR /is', $where_key)) {
                    $or = true;
                    $and = false;

                    list(,$where_key) = explode(' ', $where_key, 2);
                } elseif(preg_match('/^AND /is', $where_key)) {
                    $or = false;
                    $and = true;

                    list(,$where_key) = explode(' ', $where_key, 2);
                } else {
                    $or = false;
                    $and = true;
                }

                if(!$where_key) $where_key = 'id';

                if($or) {
                    $where_query .= ' OR ';
                } elseif($and) {
                    $where_query .= ' AND ';
                }
                
                $where_query .= '`'.$this->escape($where_key).'`=';

                if(is_integer($where_value)) {
                    $where_query .= (int)$this->escape($where_value);
                } else {
                    $where_query .= '\''.$this->escape($where_value).'\'';
                }
            }

            if($or) {
                $where_query = substr(trim($where_query), 3);
            } elseif($and) {
                $where_query = substr(trim($where_query), 4);
            }

            $query = $query . $where_query;
        } elseif($where) {
            $query .= ' WHERE `id`=';
            $query .= intval($where);
        }

        if($limit) {
            $query .= ' LIMIT '.$limit;
        }

        return $query;
    }
}

class DatabaseCollector {
    // Object-based inserter and modifier helper class

    function __construct(&$main, $table, $type) {
        $this->__main__ = $main;
        $this->__table__ = $table;
        $this->__type__ = $type;
        $this->__valid__ = true;
    }
    
    function save($settings = false) {
        if(!$this->__valid__) return false;

        $bufer = array();
        $names = get_object_vars($this);

        foreach($names as $key => $value) {
            if($key == '__main__' or $key == '__table__' or $key == '__type__' or $key == '__valid__') {
                continue;
            } else {
                $buffer[$key] = $value;
                unset($this->$key);
            }
        }

        $this->__valid__ = false;

        switch($this->__type__) {
            case 'insert':
                return $this->__main__->insert($this->__table__, $buffer);
            break;
            
            case 'update':
                return $this->__main__->update($this->__table__, $buffer, $settings);
            break;
        }
    }
}
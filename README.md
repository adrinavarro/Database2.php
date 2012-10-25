Database2.php
=============

Database2.php is an improved version of Database.php. It is upgraded for better performance, PHP 5.3 support and uses MySQLi.

### Basics

The wrapper is provided as a PHP class. You'll need `mysqli` support and PHP 5.3 or beyond.

	$db = new Database('localhost', 'root', '1337', 'mydbname');
	
Note that the link will only be established before the first query is sent. You can force the connection by calling `$db->connect()`. Link is closed using `$db->close()`.

### Getting data


All these function groups do the same. Note how the query builder is transparent and optional. Return value will be an array of objects.

	$db->fetch('SELECT `id`, `name` FROM `shops`');
	$db->fetch('id, name', 'shops');


	$db->fetch('SELECT `id`, `name` FROM `shops` WHERE `id`=5');
	$db->fetch('id, name', 'shops', 5);


	$db->fetch('SELECT `id`, `name` FROM `shops` WHERE `id`=5 OR `user`=6 LIMIT 10');
	$db->fetch('id, name', 'shops', array('id' => 5, 'OR user' => 6), 10);

Same applies for fetch_row and fetch_one:

	$db->fetch_one('SELECT `shopname` FROM `shops` WHERE `id`=1');
	$db->fetch_one('shopname', 'shops', 1);

	$db->fetch_row("SELECT * FROM `shops` WHERE `id`=1");
	$db->fetch_row('all', 'shops', 1);
	
Also note how, in the WHERE segment (for built queries), if an integer is passed, it is considered an `id` field. 

When an array is passed, the default joining form is `AND`. You can change the operator (AND, OR) by placing it in front of the key name of the array element. For example: `array('id' => 5, 'OR user' => 6)`.

*Note*: `LIKE` support is not built into the query builder.

### Inserting data

You can use raw query support to send insertions on your own. You can also use the two available forms for assisted insertions:

	$insert = $db->insert('users', array('name' => 'John Doe', 'phone' => '555675'));
	
	$user = $db->add('users');
	$user->name = 'John Doe';
	$user->phone = 555675;
	$insert = $user->save();
	
The `insert_id` value will be returned with `$insert`. If that value is null, `true` will be returned if the insertion is successful.

The second method is preferred — it will actually save you a lot of time, and both your eyes and brain will appreciate it!

### Updating data

There are similar mechanisms to update data on your database. For example:

	$changed = $db->update('users', array('phone' => '555675'), 5); // With id=5
	$changed = $db->update('users', array('phone' => '555675'), array('name' => 'John Doe')); // With name
	
	$user = $db->modify('users');
	$user->phone = 555675;
	$changed = $user->save(5); // With id=5
	
	$user = $db->modify('users');
	$user->phone = 555675;
	$changed = $user->save(array('name' => 'John Doe')); // With name
	
If no array is passed to the required `save` parameters, the passed value is expected to be an `id`. You can pass a multiple array, like you'd do to generate a `WHERE` when getting data. It is also possible to use `OR` by putting it in front of the key name (look at "Getting data" for more details).

The value returned, here stored as `$changed`, corresponds to the number of affected rows. If none, but the query is successful, `true` will be returned.

### Other comments

This library can be very useful even if you don't plan to use the query builder. Whereas the query builder is perfect for small applications operating with a reasonably small set of information, you'll likely want to retain control over the queries if you're building a big application.

The different fetcher functions, such as `Database::fetch`, `Database::fetch_one` or `Database::fetch_row`, as well as other straight-forward utilities such as `Database::escape`, will save you a lot of time over classic MySQL implementations.

The same philosophy applies to the object-based insert and update helpers. You can forget about field order or about sanitizing the input — just set the field names and their values into the object and save it to the database. Need the `insert_id`? You don't even have to ask for it!

A previous version offered query balancing between read and read-write server. This version has stripped multiple server support (sadly), but offers improved PHP 5.3 compatiblity and a number of improvements over the previous version, which is still available.

Write less, get more!
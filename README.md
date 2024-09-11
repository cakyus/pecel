# Pecel Programming Language

It blend with SQL statements.

Version : alpha

## Basic Syntax

### Instruction Separation

Terminated with a line break at the end of each statement.

### Comments

```
-- this is a comment
```

## Types

### Integers

```
var i int
i = 1
```

### Float

```
var f float
f = 0.91
```

### Boolean

```
var b bool
b = true
```

### String

```
var s string
s = 'Hello World !'
```

### Object

An object is collection of attributes.

```
var user object
var user.id int
var user.name string

user.id = 1
user.name = 'John Smith'
```

### Array

Array is a collection of values with the same type.

```
var a array int
var i int

add(a, 1)
add(a, 2)

do
  if next(a) = false then
    break
  end if
  i = get(a)
loop
```

### Array methods

`add(<array>,<value>)` add a value into an array.

`get(<array>,[index])` get value at current `index`.

`del(<array>,<index>)` delete value at `index`.

`set(<array>,<value>,[index])` update value at `index`.

`key(<array>)` get current `index`.

`next(<array>)` update `index` to the `index` of next value .
```

### Cursor

Cursor is a collection of objects which created from a sql statement.

```
var users cursor
var user object

users = select id, name from users;

do
  if next(users) = false then
    break
  end if
  print('id {user.id')
loop
```

## Variables

### Predefined Variables

### Variable Scope

## Operators

## Control Structures

### Conditional Statement

```
if i > 10 then
  -- statements ..
else if i > 9 then 
  -- statements ..
else
  -- statements ..
end if
```

### Loop

```
var i int

i = 0

do

  if i > 10 then
    break
  else if i = 0 then
    i = i + 2
    continue
  end if

  i = i + 1

loop
```

#### break

Ends execution of the loop. Accepts an optional numeric argument which tells it
how many nested enclosing structures are to be broken out of. The default value
is 1, only the immediate enclosing structure is broken out of.

```
do
  do
    break 2
  loop
loop
```

#### continue

Skip the rest of the current loop iteration and continue execution at the
condition evaluation and then the beginning of the next iteration. Accepts an
optional numeric argument which tells it how many levels of enclosing loops it
should skip to the end of. The default value is 1, thus skipping to the end of
the current loop.

## Functions

```
sub _name_ (_parameters_..) _type_
 return _variable_
end sub
```

### Example

```
sub get_user_name (id int) string
  var name string
  name = select name from users where id = {id};
  return name
end sub
```

```
var user_name string
user_name = get_user_name(1)
```
## Features

### Command Line

```sh
$ php pecel.php <file>
```

### Library

```php
<?php
// load the library
require_once('libpecel.php');
// parse source code
$pecel = pecel_load_file('file.sql');
// execute
pecel_exec($pecel);
```

### Tests

```sh
$ php tests/test.php
```

## Function Reference

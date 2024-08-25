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

### Array

```
var a int[]
a = [1,2,3,4]
a[] = 5
```

### Cursor

```
var users cursor
users = select id, name from users;

do
  if fetch(users) then
    break
  end if
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
	-- ..
else if i > 9 then 
	-- ..
else
	-- ..
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
$ php pecel.php file.sql
```

### Library

```php
<?php require_once('libpecel.php');
```

## Function Reference

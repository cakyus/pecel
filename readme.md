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
var user (id int, name string)

user.id = 1
user.name = 'John Smith'
```

### Array

Array is a collection of values.

```
var users array

users = select id, name from users;

var a array
var i int

add(a, 1)
add(a, 2)

loop

  if next(a) = false then
    break
  end if

  i = get(a)

end loop
```

## Control Structures

### Conditional Statement

```
if i > 10 then

else if i > 9 then

else

end if
```

### Loop

```
var i int

i = 0

loop

  if i > 10 then
    break
  else if i = 0 then
    i = i + 2
    continue
  end if

  i = i + 1

end loop
```

#### break

Ends execution of the loop. Accepts an optional numeric argument which tells it
how many nested enclosing structures are to be broken out of. The default value
is 1, only the immediate enclosing structure is broken out of.

```
loop
  loop
    break 2
  end loop
end loop
```

#### continue

Skip the rest of the current loop iteration and continue execution at the
condition evaluation and then the beginning of the next iteration. Accepts an
optional numeric argument which tells it how many levels of enclosing loops it
should skip to the end of. The default value is 1, thus skipping to the end of
the current loop.

## Functions

```
var c int

c = get_active_count()

sub get_active_count() int

  var user_count int

  user_count = select count(*) from users where active = 1;

  return user_count

end sub
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


# Pecel Programming Language

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
var user_id int
user_id = 1
```

### Float

```
var exchange_rate float
exchange_rate = 0.91
```

### String

```
var user_name string
user_name = 'John Smith'
```

### Array

### Cursor

```
var users cursor
users = select id, name from users;

do
  if fetch(users) = 0 then
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
if _condition_ then
  _statement_
else if _condition_ then
  _statement_
else
  _statement_
end if
```

### Loop

```
do
  [continue]
  [break]
loop
```

#### Example

```
i = 0

do

  if i > 10 then
    break
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
sub _name_ _parameters_.. as _type_
 return _variable_
end sub
```

### Example

```
sub get_user_name
  id int
as string

  var name string
  name = select name from users where id = {id};
  return name
end sub
```

```
var user_name string
user_name = get_user_name(1)
```


---
title: Model changes
description: Describes changes to the data model
extends: _layouts.documentation
section: content
---

## Make Lead-Item relationship many to one


### Schema changes

```php
Schema::dropIfExists('lead_item');

Schema::table('leads', function (Blueprint $table) {
    $table->unsignedBigInteger('item_id')->index()->nullable();
    $table->foreign('item_id')->references('id')->on('items');
});
```

### Model changes

#### Lead::item

Before:

```php
public function items(): BelongsToMany
{
    return $this->belongsToMany(Item::class, 'lead_item');
}
```

After:

```php
public function item(): BelongsTo
{
    return $this->belongsTo(Item::class);
}
```

#### Key::keyedOutItems

Before: 

```php
public function keyedOutItems(): Attribute
{
    return Attribute::make(function () {
        return Item::from('items as i')
            ->join('lead_item as li', 'i.id', '=', 'li.item_id')
            ->join('leads as l', 'li.lead_id', '=', 'l.id')
            ->where('l.key_id', $this->id)
            ->select('i.*')
            ->distinct()
            ->get();
    });
}
```

After:

```php
public function keyedOutItems(): Attribute
{
    return Attribute::make(function () {
        return Item::from('items as i')
            ->join('leads as l', 'i.item_id', '=', 'l.item_id')
            ->where('l.key_id', $this->id)
            ->select('i.*')
            ->distinct()
            ->get();
    });
}
```

This is perhaps more elegant:

```php
public function keyedOutItems(): Attribute
{
    return Attribute::make(function () {
        $leadsWithItems = Lead::where('key_id', $this->id)
            ->whereHas('item')
            ->with('item')
            ->get();

        return $leadsWithItems->map(fn ($lead) => $lead->item)
            ->unique('id');
    });
}
```


#### Key:linkedKeys


Before: 

```php
protected function linkedKeys(): Attribute
{
    return Attribute::make(get: function () {
        return Key::from('keys as k')
            ->join('items as i', 'k.item_id', '=', 'i.id')
            ->join('lead_item as li', 'i.id', '=', 'li.item_id')
            ->join('leads as l', 'li.lead_id', '=', 'l.id')
            ->where('l.key_id', $this->id)
            ->select('k.*')
            ->distinct()
            ->get();
    });
}
```

After: 

```php
protected function linkedKeys(): Attribute
{
    return Attribute::make(get: function () {
        return Key::from('keys as k')
            ->join('items as i', 'k.item_id', '=', 'i.id')
            ->join('leads as l', 'i.id', '=', 'l.item_id')
            ->where('l.key_id', $this->id)
            ->select('k.*')
            ->distinct()
            ->get();
    });
}
```

This is more expressive, so is what we are doing now:

```php
protected function linkedKeys(): Attribute
{
    return Attribute::make(get: function () {
        $leadsWithLinkedKeys = Lead::where('key_id', $this->id)
            ->whereHas('item.keysTo')
            ->with('item.keysTo')->get();

        return $leadsWithLinkedKeys->map(fn ($lead) => $lead->item->keysTo)
            ->flatten()
            ->unique();
    });
}
```

#### Item::keysIn

Before:

```php
public function keysIn(): Attribute
{
    return Attribute::make(get: function () {
        return Key::from('keys as k')
            ->join('leads as l', 'k.id', '=', 'l.key_id')
            ->join('lead_item as li', 'l.id', '=', 'li.lead_id')
            ->where('li.item_id', $this->id)
            ->select('k.*')
            ->distinct()
            ->get();
    });
}
```

After:

```php
public function keysIn(): Attribute
{
    return Attribute::make(get: function () {
        return Key::from('keys as k')
            ->join('leads as l', 'k.id', '=', 'l.key_id')
            ->where('l.item_id', $this->id)
            ->select('k.*')
            ->distinct()
            ->get();
    });
}
```

We can also do this more expressively now (so we do):

```php
public function keysIn(): Attribute
{
    return Attribute::make(get: function () {
        $leadsItemKeysOutFrom = Lead::where('item_id', $this->id)
            ->with('key')->get();

        return $leadsItemKeysOutFrom->map(fn ($lead) => $lead->key)
            ->unique();
    });
}
```
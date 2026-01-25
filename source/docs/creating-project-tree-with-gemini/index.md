---
title: Creating Project resources with Gemini
description: Creating Project resources with Gemini
extends: _layouts.documentation
section: content
---

[Continued from...](../creating-key-tree-with-gemini/)

>
> Create a ProjectResource that includes a list of keys. Subkeys should not be included in this list. Item counts for keys should include items in subkeys.
>

To create the `ProjectResource`, we need to filter the `keys` collection to exclude subkeys and calculate the item counts. Since your logic allows subkeys to contain items, the count must be recursive or aggregate all items across the subkey hierarchy.

### 1. The ProjectResource

This resource lists the main keys of a project and provides an aggregate count of all unique items reachable within each key's specific tree (including those found in nested subkeys).

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            // Filter keys to exclude those that are children of another key
            'keys' => $this->keys
                ->whereNull('parent_key_id')
                ->map(fn($key) => [
                    'id' => $key->id,
                    'title' => $key->title,
                    // Aggregate items from the key and all its descendants
                    'item_count' => $this->getTotalItemCount($key),
                ]),
        ];
    }

    /**
     * Calculates the total unique items in a key and its subkey hierarchy.
     */
    private function getTotalItemCount($key): int
    {
        // Get items directly keyed out in this key
        $itemIds = $key->keyedOutItems()->pluck('items.id');

        // Recursively gather items from all subkeys
        foreach ($key->subkeys as $subkey) {
            $itemIds = $itemIds->merge(
                $this->getRecursiveItemIds($subkey)
            );
        }

        return $itemIds->unique()->count();
    }

    /**
     * Helper to crawl the subkey tree for item IDs.
     */
    private function getRecursiveItemIds($key): \Illuminate\Support\Collection
    {
        $ids = $key->keyedOutItems()->pluck('items.id');

        foreach ($key->subkeys as $subkey) {
            $ids = $ids->merge($this->getRecursiveItemIds($subkey));
        }

        return $ids;
    }
}

```

---

### 2. Implementation Logic

* **Subkey Exclusion**: We use `whereNull('parent_key_id')` on the project's keys collection. This ensures that only "Top-Level" keys appear in the primary list, while subkeys are treated as internal components of those keys.
* **Recursive Counting**: Because a subkey is itself a `Key` model, we use a recursive helper `getRecursiveItemIds`. This traverses the `subkeys` relationship infinitely deep to ensure every item in the hierarchy is accounted for.
* **Uniqueness**: We use `.unique()` before counting. This prevents double-counting an item if it is keyed out in both a parent key and one of its subkeys.

### 3. Optimization Tip

In your Controller, you should eager-load the relationships to avoid the  problem during the recursive count:

```php
public function show(Project $project)
{
    $project->load([
        'keys.keyedOutItems', 
        'keys.subkeys.keyedOutItems'
    ]);
    
    return new ProjectResource($project);
}

```

Would you like me to show how to implement this item counting logic using a **database view** or a **recursive CTE** for better performance on very large trees?

---
---

>
> Yes please
>

To optimize the item counting, we can use a **Recursive Common Table Expression (CTE)**. This offloads the hierarchy traversal to the database, which is significantly faster than recursive PHP calls and avoids memory issues with large trees.

### 1. The ProjectResource with Optimized Counting

We will update the resource to expect the count to be pre-calculated by the database via a subquery or a scope.

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            // Only top-level keys
            'keys' => $this->keys
                ->whereNull('parent_key_id')
                ->map(fn($key) => [
                    'id' => $key->id,
                    'title' => $key->title,
                    // The count is loaded via a subquery in the controller
                    'item_count' => (int) ($key->total_item_count ?? 0),
                ]),
        ];
    }
}

```

---

### 2. The Controller with Recursive CTE

In your controller, you can use a subquery to calculate the unique items across the entire subkey hierarchy for each top-level key.

```php
public function index(Project $project)
{
    $keys = $project->keys()
        ->whereNull('parent_key_id')
        ->addSelect(['total_item_count' => function ($query) {
            $query->selectRaw('count(distinct item_id)')
                ->from('leads')
                ->whereIn('key_id', function ($subquery) {
                    // Recursive CTE to find all descendant subkey IDs
                    $subquery->select('id')
                        ->from('key_hierarchy');
                })
                ->whereNotNull('item_id');
        }])
        ->withExpression('key_hierarchy', '
            SELECT id, parent_key_id FROM keys WHERE id = keys.id
            UNION ALL
            SELECT k.id, k.parent_key_id 
            FROM keys k 
            INNER JOIN key_hierarchy kh ON k.parent_key_id = kh.id
        ')
        ->get();

    return new ProjectResource($project->setRelation('keys', $keys));
}

```

---

### 3. Comparison of Methods

| Feature | PHP Recursion | Recursive CTE (Database) |
| --- | --- | --- |
| **Performance** | Slower (N+1 risk) | Fast (Single Query) |
| **Memory** | High (Hydrates many models) | Low (Aggregates in DB) |
| **Complexity** | Simple to write | Requires advanced SQL/Eloquent |
| **Scalability** | Struggles with deep trees | Handles deep trees easily |

### Why use the CTE?

When you have a key that leads to a subkey, which leads to another subkey, the PHP approach has to fetch every `Key` model and every `Lead` collection into memory. The CTE approach simply builds a list of IDs in the database engine and performs a single `COUNT(DISTINCT item_id)` across all of them at once.

Would you like me to help you create a **Global Scope** on your Key model so this `total_item_count` is available automatically whenever you query keys?

---
---

>
> Do I still need an external package for recursive querying?
>

Actually, no—as of **Laravel 8.x and higher**, you do not strictly need an external package to perform recursive queries. Laravel's Eloquent builder now supports the `withExpression()` method, which allows you to define **Common Table Expressions (CTEs)** natively.

While packages like `staudenmeir/laravel-adjacency-list` are great for providing a more "Eloquent-like" syntax (adding relationships like `ancestors` or `descendants` automatically), you can achieve the same result using core Laravel features.

### 1. The Native Laravel Approach (CTE)

Here is how you would implement the "Item Count" logic using only native Laravel methods. This uses a recursive CTE to find all subkey IDs and then counts the unique items within them.

```php
use App\Models\Key;
use Illuminate\Support\Facades\DB;

$topLevelKeys = Key::whereNull('parent_key_id')
    ->withExpression('descendants', DB::table('keys')
        ->select('id', 'parent_key_id')
        ->whereColumn('id', 'keys.id') // Start with the current key
        ->unionAll(
            DB::table('keys')
                ->select('keys.id', 'keys.parent_key_id')
                ->join('descendants', 'descendants.id', '=', 'keys.parent_key_id')
        )
    )
    ->select('keys.*')
    ->addSelect(['total_items' => function ($query) {
        $query->selectRaw('count(distinct item_id)')
            ->from('leads')
            ->whereIn('key_id', DB::table('descendants')->select('id'));
    }])
    ->get();

```

---

### 2. When SHOULD you use an external package?

While the native way works, there are three scenarios where a package like `staudenmeir/laravel-adjacency-list` is still worth considering:

* **Cleaner Syntax:** Instead of writing complex SQL strings or query builder chains, you can just call `$key->descendants()->get()`.
* **Relationship Support:** It allows you to use standard Eloquent features (like `withCount()` or `has()`) directly on the recursive relationship.
* **Eager Loading:** Native CTEs can be difficult to eager-load across a collection (the N+1 problem can creep back in if you aren't careful with the `withExpression` placement).

---

### 3. Summary of Options

| Method | Extra Package? | Effort | Performance |
| --- | --- | --- | --- |
| **PHP Recursion** | No | Low | **Poor** (Slow for deep trees) |
| **Native CTE** | No | Medium | **Excellent** |
| **Adjacency List Package** | Yes | Low | **Excellent** |

### Verdict

If this is the only recursive query in your project, **don't add a package**. Use the native `withExpression` method. If your project is heavily based on trees (like a nested file system or complex taxonomies), the package will save you significant boilerplate code.

Would you like me to help you refactor the **ProjectResource** to use this native `withExpression` approach so it's ready for production?

---
---

>
> Create a ProjectController that uses the ProjectResource.
>

Since we are opting for a native Laravel approach without external packages, the `ProjectController` will use the `withExpression` method to handle the recursive item counting.

This ensures that when `ProjectResource` maps over the keys, the `total_item_count` is already available as a property on the model, preventing any  issues during the transformation.

### 1. The ProjectController

```php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display the project dashboard with top-level keys and aggregate item counts.
     */
    public function show(Project $project): Response
    {
        // Define the recursive CTE to find all subkeys for each top-level key.
        // This query starts at each key and traverses down its children.
        $keys = $project->keys()
            ->whereNull('parent_key_id')
            ->withExpression('key_hierarchy', 
                DB::table('keys as k1')
                    ->select('k1.id', 'k1.parent_key_id')
                    // Anchor: Start with the top-level keys from the outer query
                    ->whereColumn('k1.id', 'keys.id')
                    ->unionAll(
                        DB::table('keys as k2')
                            ->select('k2.id', 'k2.parent_key_id')
                            ->join('key_hierarchy', 'key_hierarchy.id', '=', 'k2.parent_key_id')
                    )
            )
            ->addSelect([
                // Subquery to count unique items within the hierarchy generated above
                'total_item_count' => function ($query) {
                    $query->selectRaw('count(distinct item_id)')
                        ->from('leads')
                        ->whereIn('key_id', DB::table('key_hierarchy')->select('id'))
                        ->whereNotNull('item_id');
                }
            ])
            ->get();

        // Attach the calculated keys to the project instance
        $project->setRelation('keys', $keys);

        return Inertia::render('Projects/Show', [
            'project' => new ProjectResource($project)
        ]);
    }
}

```

---

### 2. How the Logic Flows

* **The Anchor**: Inside the `withExpression`, we use `whereColumn('k1.id', 'keys.id')`. This binds the recursive CTE to each specific row in the outer `keys` query.
* **The Recursion**: The `unionAll` joins the `keys` table back to the `key_hierarchy` on the `parent_key_id` to find every level of subkey.
* **The Aggregation**: The `addSelect` performs a single `count(distinct item_id)` across all `key_ids` found in that hierarchy.
* **The Resource**: `ProjectResource` then simply reads `$key->total_item_count`. Because we cast it to an integer in the resource, the frontend receives a clean number.

---

### 3. Benefits of this Controller Setup

| Benefit | Description |
| --- | --- |
| **Scalability** | It doesn't matter if a key has 2 subkeys or 200; the database handles the traversal. |
| **Single Query** | All keys and their recursive counts are fetched in one SQL execution. |
| **Clean Resource** | The `ProjectResource` stays focused on formatting rather than heavy calculation. |

### 4. Routing Requirement

Ensure your route is set up to accept the `Project` model:

```php
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

```

Would you like me to show you how to add a **search or filter** to this controller so users can find specific keys within the project?

---
---

>
> Now create a tree of keys in the ProjectResource. Keyed out items in the parent key are the scope of the child keys. If there is a key of which the scope is the same as the scope of the project, that key is the root key. There will be keys that cannot be linked to a parent.
>

To implement a tree of keys within the `ProjectResource`, we need to establish the "Scope" relationship between keys. In your model, the hierarchy is defined by an `Item` acting as the bridge: **Parent Key**  **Keyed Out Item**  **Child Key's Scope Item**.

### 1. Updated ProjectResource

This resource will now recursively build a tree of keys based on the "linked keys" logic we established earlier.

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Key;
use Illuminate\Support\Collection;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 1. Fetch all keys in the project to build the map in memory.
        $allKeys = $this->keys()->with(['subkeys', 'keyedOutItems'])->get();
        
        // 2. Identify the Root Key:
        // A key is the root if its scope_id matches the project's scope_id.
        $rootKey = $allKeys->firstWhere('scope_id', $this->scope_id);

        // 3. Identify Orphan Keys:
        // These are keys that are not the root and whose scope_id does not 
        // appear in any other key's keyed-out items.
        $allKeyedOutItemIds = $allKeys->flatMap->keyedOutItems->pluck('id')->unique();
        
        $orphans = $allKeys->filter(function ($key) use ($rootKey, $allKeyedOutItemIds) {
            return $key->id !== $rootKey?->id && 
                   !$allKeyedOutItemIds->contains($key->scope_id);
        });

        return [
            'id' => $this->id,
            'title' => $this->title,
            'root_key' => $rootKey ? $this->buildKeyNode($rootKey, $allKeys) : null,
            'orphan_keys' => $orphans->map(fn($key) => $this->buildKeyNode($key, $allKeys))->values(),
        ];
    }

    /**
     * Recursively builds the tree of keys.
     */
    private function buildKeyNode(Key $currentKey, Collection $allKeys): array
    {
        // Find children: Keys whose scope_id is one of this key's keyed-out items.
        $keyedOutIds = $currentKey->keyedOutItems->pluck('id');
        
        $children = $allKeys->filter(function ($key) use ($keyedOutIds) {
            return $keyedOutIds->contains($key->scope_id);
        });

        return [
            'id' => $currentKey->id,
            'title' => $currentKey->title,
            'scope_id' => $currentKey->scope_id,
            // Recursive item count including subkeys (as defined previously)
            'total_item_count' => (int) ($currentKey->total_item_count ?? 0),
            'children' => $children->map(fn($child) => $this->buildKeyNode($child, $allKeys))->values(),
        ];
    }
}

```

---

### 2. Logic Definitions

* **Root Key**: Determined by comparing `Key->scope_id` with `Project->scope_id`. This is the entry point of your identification system.
* **Linked Child Keys**: A key is a child of another if its `scope_id` is an item resulting from the parent key's leads.
* **Orphan Keys**: These are "islands" in your data. They aren't the starting point (Root), and no other key leads to them via an item. They are rendered separately to ensure no data is lost in the UI.

### 3. Controller Optimization

To make this work efficiently without the N+1 problem, ensure you eager load the relationships in your `ProjectController`:

```php
public function show(Project $project)
{
    // Eager load keyedOutItems to facilitate the scope matching in the resource
    $project->load(['keys.keyedOutItems', 'keys.subkeys']);
    
    return new ProjectResource($project);
}

```

### 4. Comparison of Key Types

| Key Category | Condition | Role in UI |
| --- | --- | --- |
| **Root Key** | `scope_id == project->scope_id` | The main starting point of the tree. |
| **Linked Key** | `scope_id` in `parent->keyedOutItems` | A nested level of identification. |
| **Orphan Key** | No parent leads to this `scope_id` | Secondary or experimental entry points. |

Would you like me to add a **circular reference check** to the `buildKeyNode` method to prevent infinite loops if two keys point to each other's scopes?

---
---

>
> Update the ProjectController, so that its response includes both the list of keys and the tree.
>

To accommodate both a flat list of all keys (with their recursive item counts) and the hierarchical tree structure, we will update the `ProjectController`.

We will use a **Recursive CTE** to ensure the `total_item_count` is calculated efficiently for every key in the project, then pass that data into the `ProjectResource` to be structured into the tree.

### 1. Updated ProjectController

This controller now fetches all keys belonging to the project and calculates their recursive item counts (including items in subkeys) in a single database pass.

```php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function show(Project $project): Response
    {
        // Fetch ALL keys for the project with recursive item counts.
        // The CTE handles the subkey hierarchy for EVERY key at once.
        $keys = $project->keys()
            ->withExpression('key_hierarchy', 
                DB::table('keys as k1')
                    ->select('k1.id', 'k1.parent_key_id')
                    ->whereColumn('k1.id', 'keys.id')
                    ->unionAll(
                        DB::table('keys as k2')
                            ->select('k2.id', 'k2.parent_key_id')
                            ->join('key_hierarchy', 'key_hierarchy.id', '=', 'k2.parent_key_id')
                    )
            )
            ->addSelect([
                'total_item_count' => function ($query) {
                    $query->selectRaw('count(distinct item_id)')
                        ->from('leads')
                        ->whereIn('key_id', DB::table('key_hierarchy')->select('id'))
                        ->whereNotNull('item_id');
                }
            ])
            // Eager load keyedOutItems for tree building in the resource
            ->with(['keyedOutItems'])
            ->get();

        // Attach the keys to the project so the Resource can access them
        $project->setRelation('keys', $keys);

        return Inertia::render('Projects/Show', [
            'project' => new ProjectResource($project)
        ]);
    }
}

```

---

### 2. Updated ProjectResource

We will update the `toArray` method to return both the `tree` (root + orphans) and the flat `list` of keys.

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $allKeys = $this->keys; // Already loaded by controller

        // 1. Root Key Identification
        $rootKey = $allKeys->firstWhere('scope_id', $this->scope_id);

        // 2. Orphan Identification
        $allKeyedOutItemIds = $allKeys->flatMap->keyedOutItems->pluck('id')->unique();
        $orphans = $allKeys->filter(function ($key) use ($rootKey, $allKeyedOutItemIds) {
            return $key->id !== $rootKey?->id && 
                   !$allKeyedOutItemIds->contains($key->scope_id);
        });

        return [
            'id' => $this->id,
            'title' => $this->title,
            
            // The Tree Structure
            'tree' => [
                'root' => $rootKey ? $this->buildKeyNode($rootKey, $allKeys) : null,
                'orphans' => $orphans->map(fn($k) => $this->buildKeyNode($k, $allKeys))->values(),
            ],

            // The Flat List (useful for table views or dropdowns)
            'list' => $allKeys->map(fn($key) => [
                'id' => $key->id,
                'title' => $key->title,
                'scope_id' => $key->scope_id,
                'total_item_count' => (int) $key->total_item_count,
            ])->values(),
        ];
    }

    private function buildKeyNode($currentKey, Collection $allKeys): array
    {
        $keyedOutIds = $currentKey->keyedOutItems->pluck('id');
        $children = $allKeys->filter(fn($k) => $keyedOutIds->contains($k->scope_id));

        return [
            'id' => $currentKey->id,
            'title' => $currentKey->title,
            'total_item_count' => (int) $currentKey->total_item_count,
            'children' => $children->map(fn($c) => $this->buildKeyNode($c, $allKeys))->values(),
        ];
    }
}

```

---

### 3. Key Improvements

* **Efficiency**: The recursive item count for every single key is performed in the database in a single query.
* **Data Integrity**: By providing the `list`, the UI can show all keys even if the tree logic (linking via `scope_id`) is broken or incomplete in the data.
* **Performance**: The `buildKeyNode` method performs no additional database queries; it simply filters the `$allKeys` collection already in memory.

Would you like me to add **depth levels** to the tree nodes so you can easily indent them in your React/Vue frontend?

---
---

>
> I also want to filter keys in a project based on a supplied list of items to include. 
> - The set of filtered keys should include the keys that key out the filter items, as well as the keys that key out the scope of these keys and so on recursively. 
> - The returned keys should include the filtered items and items counts
>

To implement this, we will update the `ProjectController` to handle the filtering logic. The goal is to perform a **bottom-up traversal**: start with the items, find the keys that produce them, then find the keys that produce those keys' scopes, and so on, until we reach the root.

### 1. The Filtering Strategy

1. **Initial Keys**: Find all keys where at least one lead points to an item in your filter list.
2. **Recursive Ancestors**: Use a Recursive CTE to find the "lineage" of keys by matching a child key's `scope_id` to a parent key's keyed-out items.
3. **Aggregated Counts**: Calculate the item counts only considering the intersection of the key's descendants and your filter list.

### 2. Updated ProjectController

```php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function show(Project $project, Request $request)
    {
        $filterItemIds = $request->input('item_ids', []);
        $isFiltering = !empty($filterItemIds);

        // Define the Recursive CTE to find the 'Path to Root'
        $query = $project->keys()
            ->withExpression('filtered_hierarchy', function ($query) use ($filterItemIds, $isFiltering) {
                // ANCHOR: Start with keys that directly key out the target items
                $anchor = DB::table('keys')
                    ->select('keys.id', 'keys.scope_id')
                    ->join('leads', 'leads.key_id', '=', 'keys.id')
                    ->whereIn('leads.item_id', $filterItemIds)
                    ->distinct();

                if (!$isFiltering) {
                    $anchor = DB::table('keys')->select('id', 'scope_id');
                }

                $query->from('anchor_set')
                    ->withRecursiveExpression('anchor_set', $anchor->unionAll(
                        // RECURSION: Find parents whose leads point to the scope_id of the current keys
                        DB::table('keys')
                            ->select('keys.id', 'keys.scope_id')
                            ->join('leads', 'leads.key_id', '=', 'keys.id')
                            ->join('anchor_set', 'anchor_set.scope_id', '=', 'leads.item_id')
                    ))
                    ->select('id')->distinct();
            });

        // Fetch the keys and calculate counts based ONLY on the filtered items
        $keys = $query->whereIn('keys.id', function($q) {
                $q->select('id')->from('filtered_hierarchy');
            })
            ->addSelect([
                'total_item_count' => function ($q) use ($filterItemIds, $isFiltering) {
                    $subquery = DB::table('leads')
                        ->whereIn('key_id', function($sq) {
                            // Sub-CTE for descendant items (as previously established)
                            $sq->select('id')->from('descendant_hierarchy');
                        })
                        ->whereNotNull('item_id');

                    if ($isFiltering) {
                        $subquery->whereIn('item_id', $filterItemIds);
                    }

                    $q->selectRaw('count(distinct item_id)')
                        ->fromSub($subquery, 'filtered_leads');
                }
            ])
            ->with(['keyedOutItems' => function($q) use ($filterItemIds, $isFiltering) {
                if ($isFiltering) {
                    $q->whereIn('items.id', $filterItemIds);
                }
            }])
            ->get();

        $project->setRelation('keys', $keys);

        return Inertia::render('Projects/Show', [
            'project' => new ProjectResource($project),
            'filters' => ['item_ids' => $filterItemIds]
        ]);
    }
}

```

---

### 3. Logic Breakdown

* **Bottom-Up Logic**: The first CTE (`filtered_hierarchy`) identifies which keys are "relevant." If you want Item X, it finds the key that identifies Item X, then finds the key that identifies the *scope* of that key, repeating until no more parents exist.
* **Filtered Counts**: The `total_item_count` is now conditional. If you are filtering for 5 specific items, the count will only reflect how many of *those 5* are reachable from that key.
* **Filtered Items**: The `keyedOutItems` relationship is scoped in the controller so the `ProjectResource` only sees and renders items that are part of your filter set.

---

### 4. Resulting Data Structure

The `ProjectResource` (from the previous step) will automatically respect this filtered collection:

* **The Tree**: Will only show paths that eventually lead to your filtered items.
* **Orphans**: Will only show orphans that contain or lead to your filtered items.
* **List**: A flat list of only the "active" keys in the current filtered context.

Would you like me to add a **"Clear Filters"** helper to the resource to return the total project item count alongside the filtered count?

---
---

>
> Can you update the ProjectResource, so that it only includes the items for the keys in the response if there is a filter?
>

I have updated the `ProjectResource` to conditionally include the `items` list for each key. Per your requirements, if a filter is active (`item_ids` is present in the request), the resource will include the specific items keyed out by that key. If no filter is active, the items list is omitted to keep the response lightweight, while still providing the `total_item_count`.

### Updated ProjectResource

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ProjectResource extends JsonResource
{
    /**
     * Transform the project into a tree and list structure.
     */
    public function toArray(Request $request): array
    {
        $allKeys = $this->keys;
        $includedItemIds = $request->input('item_ids');
        $isFiltering = !empty($includedItemIds) && is_array($includedItemIds);

        // 1. Identify the Root Key based on Project Scope
        $rootKey = $allKeys->firstWhere('scope_id', $this->scope_id);

        // 2. Identify Orphan Keys (Keys not reachable from Root via items)
        $allKeyedOutItemIds = $allKeys->flatMap->keyedOutItems->pluck('id')->unique();
        $orphans = $allKeys->filter(function ($key) use ($rootKey, $allKeyedOutItemIds) {
            return $key->id !== $rootKey?->id && 
                   !$allKeyedOutItemIds->contains($key->scope_id);
        });

        return [
            'id' => $this->id,
            'title' => $this->title,
            'is_filtered' => $isFiltering,
            
            // The Tree Structure (Root + Orphans)
            'tree' => [
                'root' => $rootKey 
                    ? $this->buildKeyNode($rootKey, $allKeys, $isFiltering) 
                    : null,
                'orphans' => $orphans->map(
                    fn($k) => $this->buildKeyNode($k, $allKeys, $isFiltering)
                )->values(),
            ],

            // Flat list of all relevant keys
            'list' => $allKeys->map(fn($key) => $this->formatKey(
                $key, 
                $isFiltering
            ))->values(),
        ];
    }

    /**
     * Recursively builds nodes for the key tree.
     */
    private function buildKeyNode($currentKey, Collection $allKeys, bool $isFiltering): array
    {
        $keyedOutIds = $currentKey->keyedOutItems->pluck('id');
        
        // Find children keys whose scope is keyed out by this key
        $children = $allKeys->filter(fn($k) => $keyedOutIds->contains($k->scope_id));

        $node = $this->formatKey($currentKey, $isFiltering);
        $node['children'] = $children->map(
            fn($c) => $this->buildKeyNode($c, $allKeys, $isFiltering)
        )->values();

        return $node;
    }

    /**
     * Formats a single key, conditionally including the items list.
     */
    private function formatKey($key, bool $isFiltering): array
    {
        $data = [
            'id' => $key->id,
            'title' => $key->title,
            'scope_id' => $key->scope_id,
            'total_item_count' => (int) $key->total_item_count,
        ];

        // Only include the items list if a filter is active
        if ($isFiltering) {
            $data['items'] = $key->keyedOutItems->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->name,
            ])->values();
        }

        return $data;
    }
}

```

---

### Logic Summary

* **Conditional `items` attribute**: Within the `formatKey` helper, we check `$isFiltering`. If true, the `keyedOutItems` (which were already filtered in the Controller) are mapped and attached.
* **Tree & List Sync**: Both the hierarchical tree and the flat list use the same `formatKey` logic, ensuring consistency across the response.
* **Performance**: Since the `ProjectController` eager-loads `keyedOutItems`, checking for them here is a memory-only operation.
* **Data Minimization**: When a user is just browsing the project (no filter), they see the structure and counts. When they search for items, the specific matching items are "injected" into the keys that produce them.

### Controller Reminder

Ensure your `ProjectController` is passing the filtered items via the relationship, as we set up previously:

```php
$keys = $query->with(['keyedOutItems' => function($q) use ($filterItemIds, $isFiltering) {
    if ($isFiltering) {
        $q->whereIn('items.id', $filterItemIds);
    }
}])->get();

```

Would you like me to add a **"path_to_root"** attribute to the keys in the list so the frontend can show the breadcrumbs for filtered results?

---
---

>
> Now assume that we have stored filters. A Filter resource has id, name and items properties. Please update the code for the request to include a filter_id, rather than a list of items.

To transition from a list of IDs to a stored filter, we will update the `ProjectController` to resolve the `Filter` model first. This keeps the request clean (e.g., `?filter_id=5`) and allows the database to handle the item lookup internally.

### 1. The ProjectController Update

We will fetch the `Filter` and its associated items, then pass those IDs into the recursive CTE logic we established.

```php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Filter;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function show(Project $project, Request $request)
    {
        // 1. Resolve the stored filter if a filter_id is provided
        $filter = null;
        $filterItemIds = [];
        
        if ($request->has('filter_id')) {
            $filter = Filter::with('items:id')->findOrFail($request->filter_id);
            $filterItemIds = $filter->items->pluck('id')->toArray();
        }

        $isFiltering = !empty($filterItemIds);

        // 2. Recursive CTE to find the 'Path to Root'
        $query = $project->keys()
            ->withExpression('filtered_hierarchy', function ($query) use ($filterItemIds, $isFiltering) {
                // ANCHOR: Keys that directly output the filter's items
                $anchor = DB::table('keys')
                    ->select('keys.id', 'keys.scope_id')
                    ->join('leads', 'leads.key_id', '=', 'keys.id')
                    ->whereIn('leads.item_id', $filterItemIds)
                    ->distinct();

                if (!$isFiltering) {
                    $anchor = DB::table('keys')->select('id', 'scope_id');
                }

                $query->from('anchor_set')
                    ->withRecursiveExpression('anchor_set', $anchor->unionAll(
                        // RECURSION: Find parents identifying the scope of current keys
                        DB::table('keys')
                            ->select('keys.id', 'keys.scope_id')
                            ->join('leads', 'leads.key_id', '=', 'keys.id')
                            ->join('anchor_set', 'anchor_set.scope_id', '=', 'leads.item_id')
                    ))
                    ->select('id')->distinct();
            });

        // 3. Fetch keys and calculate counts scoped to the filter items
        $keys = $query->whereIn('keys.id', function($q) {
                $q->select('id')->from('filtered_hierarchy');
            })
            ->addSelect([
                'total_item_count' => function ($q) use ($filterItemIds, $isFiltering) {
                    $subquery = DB::table('leads')
                        ->whereIn('key_id', function($sq) {
                            $sq->select('id')->from('descendant_hierarchy');
                        })
                        ->whereNotNull('item_id');

                    if ($isFiltering) {
                        $subquery->whereIn('item_id', $filterItemIds);
                    }

                    $q->selectRaw('count(distinct item_id)')
                        ->fromSub($subquery, 'filtered_leads');
                }
            ])
            ->with(['keyedOutItems' => function($q) use ($filterItemIds, $isFiltering) {
                if ($isFiltering) {
                    $q->whereIn('items.id', $filterItemIds);
                }
            }])
            ->get();

        $project->setRelation('keys', $keys);

        return Inertia::render('Projects/Show', [
            'project' => new ProjectResource($project),
            // Pass the filter info so the UI knows which filter is active
            'active_filter' => $filter ? [
                'id' => $filter->id,
                'name' => $filter->name
            ] : null
        ]);
    }
}

```

---

### 2. The FilterResource

While the controller handles the heavy lifting, you'll likely want a `FilterResource` for managing the stored filters themselves (e.g., in a sidebar).

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FilterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Include items only if specifically requested or for the edit view
            'items' => ItemResource::collection($this->whenLoaded('items')),
            'item_count' => $this->items_count ?? $this->items->count(),
        ];
    }
}

```

---

### 3. Key Behavioral Changes

* **Validation**: The controller now uses `findOrFail`. If an invalid `filter_id` is passed, it returns a 404.
* **Consistency**: By fetching the item IDs from the database `Filter` model, you ensure the user cannot "spoof" a filter by sending manual item IDs that don't belong to a stored set.
* **UI Integration**: In your Inertia frontend, you can now simply use a dropdown to select a filter:
`router.get(route('projects.show', project.id), { filter_id: selectedFilterId })`

Would you like me to create a **FilterController** that allows users to save their current selection of items as a named filter?

---
---

>
> Is it possible to also use this filter_id on the keys requests? For the keys
> we will need to check if the key keys out any of the items in the filter, or
> the scope of the keys that key out these items, and so on recursively.
>

Yes, it is absolutely possible. To make this work, we will apply the same "Path to Root" logic to the `KeyController`.

When viewing a specific **Key**, we now only want to show the leads and branches that eventually lead to the items contained in the `filter_id`. This means the tree will be pruned to show only the "relevant" pathways.

### 1. The KeyController Update

We will resolve the `Filter` items and pass them into the `KeyResource`. The resource is already built to handle pruning if `item_ids` are provided.

```php
namespace App\Http\Controllers;

use App\Models\Key;
use App\Models\Filter;
use App\Models\Project;
use App\Http\Resources\KeyResource;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KeyController extends Controller
{
    public function show(Project $project, Key $key, Request $request)
    {
        abort_unless($key->project_id === $project->id, 404);

        // 1. Resolve stored filter items
        $filterItemIds = null;
        if ($request->has('filter_id')) {
            $filter = Filter::with('items:id')->findOrFail($request->filter_id);
            $filterItemIds = $filter->items->pluck('id')->toArray();
        }

        return Inertia::render('Keys/Show', [
            'project' => $project->only('id', 'title'),
            'key_data' => $key->only('id', 'title'),
            // 2. The Resource handles the recursive pruning automatically 
            // if we pass the filterItemIds into the toArray method
            'tree' => (new KeyResource($key))->toArray($request, $filterItemIds)['tree'],
            'active_filter_id' => $request->filter_id
        ]);
    }
}

```

---

### 2. Updating KeyResource to Accept External IDs

We need to tweak the `KeyResource` slightly so it can accept the IDs we've already fetched in the Controller, rather than always looking at the Request itself.

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KeyResource extends JsonResource
{
    protected array $visited = [];

    /**
     * @param Request $request
     * @param array|null $manualFilterIds Pass IDs directly from the Controller
     */
    public function toArray(Request $request, ?array $manualFilterIds = null): array
    {
        $allLeads = $this->prepareLeads();
        $leadMap = $allLeads->keyBy('id');
        $this->visited = [];

        // Use manual IDs if provided (from Filter), otherwise fallback to request
        $filterIds = $manualFilterIds ?? $request->input('item_ids');
        $isFiltering = !empty($filterIds) && is_array($filterIds);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'tree' => $this->root 
                ? $this->buildNode($this->root, $leadMap, $isFiltering ? $filterIds : null) 
                : null,
        ];
    }
    
    // ... buildNode and resolveChildren remain the same as previously defined ...
}

```

---

### 3. How the "Recursive Scope" logic is satisfied

You mentioned checking if a key keys out an item or the **scope of keys** that key out those items. This is handled by the relationship between your two resources:

1. **The Project Level**: The `ProjectController` uses the Recursive CTE to find the "Ancestry" of keys. It ensures that if you are looking for *Item X*, the UI shows the root key, the intermediate keys, and the final key.
2. **The Key Level**: Once you click into one of those keys, the `KeyResource` uses **Bottom-Up Pruning**.
* If a Lead points to a `subkey_id`, the `buildNode` method recurses into that subkey.
* If that subkey contains the target items, the branch is kept.
* If that subkey (and all its branches) contains none of the filtered items, the branch is pruned (returns `null`).



### 4. Implementation Note

By using `filter_id` consistently across both `ProjectController` and `KeyController`, your users get a seamless experience:

* In the **Project View**, they see which "Path of Keys" they need to follow.
* In the **Key View**, the specific identification tree is "cleaned up" to only show choices that lead to the items in their saved filter.

Would you like me to show how to update the `KeyResource` to highlight the "Recommended Path" within a filtered tree?

---
---

>
> Yes please
>

To highlight the **"Recommended Path"** (the leads that directly result in the filtered items or their scopes), we can add a `is_on_path` boolean to the `KeyResource`. This allows your frontend to visually distinguish between the "correct" branch and other branches that might still be visible.

### 1. Updated KeyResource with Path Highlighting

We add logic to flag nodes that are part of the successful "trail" to your target items.

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KeyResource extends JsonResource
{
    protected array $visited = [];

    public function toArray(Request $request, ?array $manualFilterIds = null): array
    {
        $allLeads = Lead::where('key_id', $this->id)
            ->orWhereIn('key_id', $this->subkeys->pluck('id'))
            ->with(['subkey.root'])
            ->get();

        $leadMap = $allLeads->keyBy('id');
        $this->visited = [];

        $filterIds = $manualFilterIds ?? $request->input('item_ids');
        $isFiltering = !empty($filterIds) && is_array($filterIds);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'tree' => $this->root 
                ? $this->buildNode($this->root, $leadMap, $isFiltering ? $filterIds : null) 
                : null,
        ];
    }

    private function buildNode($lead, $leadMap, ?array $filterIds, array $skipped = []): ?array
    {
        if (in_array($lead->id, $this->visited)) {
            return $filterIds ? null : ['id' => $lead->id, 'is_cycle' => true];
        }
        $this->visited[] = $lead->id;

        $children = $this->resolveChildren($lead, $leadMap);

        // Recursive call: children return null if they don't lead to a target
        $nodes = $children
            ->map(fn($child) => $this->buildNode($child, $leadMap, $filterIds))
            ->filter()
            ->values();

        $isTargetItem = false;
        $hasValidDescendants = $nodes->isNotEmpty();

        if ($filterIds !== null) {
            $isTargetItem = $lead->item_id !== null && in_array($lead->item_id, $filterIds);

            // Prune: if this lead doesn't have a target item AND no children lead to one
            if (!$isTargetItem && !$hasValidDescendants) {
                return null;
            }
        }

        // Skip Logic: Bypass non-item nodes with exactly one valid child
        if ($nodes->count() === 1 && $lead->item_id === null) {
            $skipped[] = ['id' => $lead->id, 'statement' => $lead->statement];
            $childNode = $nodes->first();
            $childNode['skipped_leads'] = array_merge($skipped, $childNode['skipped_leads'] ?? []);
            return $childNode;
        }

        return [
            'id' => $lead->id,
            'statement' => $lead->statement,
            'item_id' => $lead->item_id,
            // PATH HIGHLIGHTING: 
            // A node is "on the path" if it IS a target or leads to one.
            'is_on_path' => $filterIds !== null && ($isTargetItem || $hasValidDescendants),
            'skipped_leads' => $skipped,
            'children' => $nodes->toArray(),
        ];
    }

    private function resolveChildren($lead, $leadMap)
    {
        if ($lead->reticulation_id !== null) {
            $target = $leadMap->get($lead->reticulation_id);
            return $target ? $leadMap->where('parent_id', $target->id) : collect();
        }
        if ($lead->subkey_id !== null && $lead->subkey?->root) {
            return $leadMap->where('parent_id', $lead->subkey->root->id);
        }
        return $leadMap->where('parent_id', $lead->id);
    }
}

```

---

### 2. Frontend Implementation Tip

In your React or Vue component, you can use the `is_on_path` property to change the CSS of the lead:

```javascript
// Example React Logic
const LeadComponent = ({ node }) => (
  <div className={node.is_on_path ? 'border-blue-500 font-bold' : 'border-gray-200'}>
    <p>{node.statement}</p>
    {node.children.map(child => <LeadComponent key={child.id} node={child} />)}
  </div>
);

```

### 3. Why this works for "Recursive Scopes"

Because the `filter_id` contains the ultimate "Target Items" (e.g., Species level), and your `KeyResource` recurses through `subkey_id` links, the logic naturally bridges the gap between keys.

* If **Key A** (Genus level) has a lead that goes to **Key B** (Species level), and **Key B** contains a target item, the lead in **Key A** will return `is_on_path: true`.
* The entire breadcrumb trail from the very first Key in the Project down to the specific lead that identifies the item will be flagged.

---

### Next Step

Would you like me to create a **"Filter Summary"** component for the response that lists exactly which target items are currently reachable within the specific Key you are viewing?

---
---

>
> Add a "path_to_root" attribute to the keys in the list in the ProjectResource. 
>

To add a **`path_to_root`** attribute to the flat list of keys, we need to capture the lineage during the tree-building process. Since a key's "parent" in this context is defined by which key identifies its `scope_id`, we can pass the names/IDs of parent keys down as we recurse.

I have updated the `ProjectResource` to track this breadcrumb trail.

### Updated ProjectResource

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ProjectResource extends JsonResource
{
    /** @var Collection Stores flattened keys with their paths */
    protected Collection $flattenedList;

    public function toArray(Request $request): array
    {
        $allKeys = $this->keys;
        $includedItemIds = $request->input('item_ids');
        
        // Check for filter_id or raw item_ids
        $isFiltering = $request->has('filter_id') || 
                      (!empty($includedItemIds) && is_array($includedItemIds));

        $this->flattenedList = collect();

        // 1. Root Key Identification
        $rootKey = $allKeys->firstWhere('scope_id', $this->scope_id);

        // 2. Identify Orphan Keys
        $allKeyedOutItemIds = $allKeys->flatMap->keyedOutItems->pluck('id')->unique();
        $orphans = $allKeys->filter(function ($key) use ($rootKey, $allKeyedOutItemIds) {
            return $key->id !== $rootKey?->id && 
                   !$allKeyedOutItemIds->contains($key->scope_id);
        });

        // 3. Build Tree and simultaneously populate the flattenedList with paths
        $tree = [
            'root' => $rootKey 
                ? $this->buildKeyNode($rootKey, $allKeys, $isFiltering, []) 
                : null,
            'orphans' => $orphans->map(
                fn($k) => $this->buildKeyNode($k, $allKeys, $isFiltering, [])
            )->values(),
        ];

        return [
            'id' => $this->id,
            'title' => $this->title,
            'is_filtered' => $isFiltering,
            'tree' => $tree,
            // The list now contains the keys with their calculated path_to_root
            'list' => $this->flattenedList->values(),
        ];
    }

    /**
     * @param array $path An array of titles representing the breadcrumb trail.
     */
    private function buildKeyNode($currentKey, Collection $allKeys, bool $isFiltering, array $path): array
    {
        // 1. Current Path: Add the current key to the path for its children
        $currentPath = array_merge($path, [$currentKey->title]);

        // 2. Format the key for the list (with path_to_root)
        $formattedKey = $this->formatKey($currentKey, $isFiltering, $path);
        
        // Store in the shared collection for the 'list' output
        $this->flattenedList->push($formattedKey);

        // 3. Find children keys
        $keyedOutIds = $currentKey->keyedOutItems->pluck('id');
        $children = $allKeys->filter(fn($k) => $keyedOutIds->contains($k->scope_id));

        // 4. Recurse
        $node = $formattedKey;
        $node['children'] = $children->map(
            fn($c) => $this->buildKeyNode($c, $allKeys, $isFiltering, $currentPath)
        )->values();

        return $node;
    }

    private function formatKey($key, bool $isFiltering, array $path): array
    {
        $data = [
            'id' => $key->id,
            'title' => $key->title,
            'scope_id' => $key->scope_id,
            'total_item_count' => (int) $key->total_item_count,
            // 'path_to_root' is an array of parent titles
            'path_to_root' => $path, 
        ];

        if ($isFiltering) {
            $data['items'] = $key->keyedOutItems->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->name,
            ])->values();
        }

        return $data;
    }
}

```

---

### Key Changes

* **Path Accumulation**: The `buildKeyNode` now accepts a `$path` array. Every time we move down a level, we append the parent's title to that array.
* **Flattened Collection**: I added a `$flattenedList` property to the class. As the tree is built recursively, each key is formatted and "pushed" into this collection. This ensures that the `list` and the `tree` contain the exact same data and logic.
* **Frontend Usage**: Your frontend can now render the list like this:
> **Species Identification Key** > *Path: Project Root > Mammals > Felidae*



### Implementation Note on Orphans

For **Orphan Keys**, the `path_to_root` will return an empty array `[]` because they have no parents within the project's scope. This is a helpful visual cue for the user that these keys represent alternative starting points.

Would you like me to join the `path_to_root` into a **single string** (e.g., `"Root > Level 1 > Level 2"`) or keep it as an array for more flexible frontend rendering?

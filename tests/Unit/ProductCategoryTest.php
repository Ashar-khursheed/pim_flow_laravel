<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class ProductCategoryTest extends TestCase
{
    public function test_most_specific_category_logic()
    {
        // Simulate categories
        // Parent Category (Kitchen Supplies)
        $parent = new Category();
        $parent->id = 1;
        $parent->name = 'Kitchen Supplies';
        $parent->parent_id = null;

        // Child Category (Graters & Peelers)
        $child = new Category();
        $child->id = 2;
        $child->name = 'Graters & Peelers';
        $child->parent_id = 1;

        // Another unrelated category (just in case)
        $other = new Category();
        $other->id = 3;
        $other->name = 'Other';
        $other->parent_id = null;


        // Case 1: Product assigned to both Parent and Child
        $product = new Product();
        $product->setRelation('categories', new Collection([$parent, $child]));
        
        $mostSpecific = $product->mostSpecificCategory();
        $this->assertEquals(2, $mostSpecific->id, 'Should return child category when both parent and child are assigned');


        // Case 2: Product assigned only to Parent
        $product2 = new Product();
        $product2->setRelation('categories', new Collection([$parent]));

        $mostSpecific2 = $product2->mostSpecificCategory();
        $this->assertEquals(1, $mostSpecific2->id, 'Should return parent category when only parent is assigned');

        // Case 3: Product assigned to Child and Other (Child is depth 1, Other is depth 0 but no relation)
        // The logic simply picks leaf nodes in the graph of assigned categories.
        // potentially problematic if multiple leaves exists, but logic says sortByDesc('id')
        $product3 = new Product();
        $product3->setRelation('categories', new Collection([$child, $other]));

        $mostSpecific3 = $product3->mostSpecificCategory();
        // Both are leaves in the context of this collection (Other has no children in this list, Child has no children in this list)
        // It should pick the one with highest ID (3 or 2). 
        // Wait, Child has parent_id=1. Parent 1 is NOT in the collection. So Child is also a root in this subgraph. 
        // So both are leaves.
        $this->assertTrue(in_array($mostSpecific3->id, [2, 3]), 'Should return one of the leaves');
        $this->assertEquals(3, $mostSpecific3->id, 'Should return Other (id 3) because it has higher ID');


        // Case 4: Grandparent checking
        $grandparent = new Category();
        $grandparent->id = 10;
        $grandparent->parent_id = null;

        $parentOfChild = new Category();
        $parentOfChild->id = 11;
        $parentOfChild->parent_id = 10;

        $childOfParent = new Category();
        $childOfParent->id = 12;
        $childOfParent->parent_id = 11;

        $product4 = new Product();
        $product4->setRelation('categories', new Collection([$grandparent, $parentOfChild, $childOfParent]));
        
        $mostSpecific4 = $product4->mostSpecificCategory();
        $this->assertEquals(12, $mostSpecific4->id, 'Should return deepest child in 3-level hierarchy');
    }
}

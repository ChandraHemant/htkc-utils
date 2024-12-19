<?php

namespace ChandraHemant\HtkcUtils;

use ChandraHemant\HtkcUtils\PaginatedResource;
use ChandraHemant\HtkcUtils\ReturnHelper;

/**
 * Class: DynamicSearchHelper
 * Author: Hemant Kumar Chandra
 * Category: Helpers
 *
 * This class provides dynamic search functionality for Laravel applications.
 * It supports searching within nested relationships and applying complex query conditions.
 */
class DynamicSearchHelper
{
    private $searchColumns;
    private $withPagination;
    private $queryMode;
    private $isApi;

    /**
     * Constructor method to initialize the class with required parameters.
     *
     * @param array $searchColumns
     *   An array specifying additional conditions (e.g., 'comp_id') to apply to the query.
     *
     * @param bool $withPagination
     *   Whether or not to apply pagination to the query results.
     *
     * @param bool $queryMode
     *   Whether to return the query builder instance directly instead of the results.
     *
     * @param bool $isApi
     *   Whether the response should be in API format (JSON).
     */
    public function __construct(array $searchColumns = [], bool $withPagination = false, bool $queryMode = false, bool $isApi = true)
    {
        $this->searchColumns = $searchColumns;
        $this->withPagination = $withPagination;
        $this->queryMode = $queryMode;
        $this->isApi = $isApi;
    }

    /**
     * Perform a dynamic search on the specified model, with optional pagination.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Support\Collection
     *   The search results, either as a JSON response or a collection, depending on query mode.
     */
    public function getDynamicSearchData()
    {
        // Validate the request
        request()->validate([
            'model' => 'required|string',
            'resource' => 'required|string',
            'column' => 'required|array',
            'value' => 'nullable|string',
            'orderBy' => 'sometimes|array',
            'orderBy.column' => 'required_with:orderBy|string',
            'orderBy.direction' => 'required_with:orderBy|in:asc,desc'
        ]);

        // Extract the model and resource class names from the request
        $modelClass = 'App\\Models\\' . request()->input('model');
        $resourceClass = 'App\\Http\\Resources\\' . request()->input('resource');

        // Ensure the classes exist
        if (!class_exists($modelClass) || !class_exists($resourceClass)) {
            return response()->json(['error' => 'Invalid model or resource class.'], 400);
        }

        // Begin a query on the specified model, applying any additional conditions
        $model = $this->searchColumns ? $modelClass::where($this->searchColumns) : $modelClass::query();

        // Apply dynamic search conditions to the query
        $this->applyDynamicConditions($model);

        // Apply ordering if specified
        $this->applyOrdering($model);

        // Apply pagination if required
        $results = $this->withPagination ? $this->applyPagination($model) : $model->get();

        // Handle the case where no results are found
        if ($results->isEmpty()) {
            return response()->json(['result' => [], 'status' => false], 404);
        }

        // Return results based on the query mode
        if ($this->queryMode) {
            return $results;  // Return the query builder or query result directly
        } else {
            // Return the results using the specified resource
            return $this->withPagination
                ? ReturnHelper::jsonApiReturn(new PaginatedResource($results, $resourceClass), $this->isApi)
                : ReturnHelper::jsonApiReturn($resourceClass::collection($results), $this->isApi);
        }
    }

    /**
     * Apply dynamic search conditions to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $model
     *   The query builder instance.
     */
    private function applyDynamicConditions($model)
    {
        $searchValue = request()->input('value');
        
        // If no search columns are provided, use the ones from class initialization
        $searchColumns = request()->input('column', $this->searchColumns);
        
        if (empty($searchColumns) || empty($searchValue)) {
            return;
        }

        // Validate all relationship paths before applying conditions
        foreach ((array)$searchColumns as $columnPath) {
            if (strpos($columnPath, '.') !== false) {
                $parts = explode('.', $columnPath);
                array_pop($parts); // Remove the column name
                $relationshipPath = implode('.', $parts);
                
                try {
                    $this->validateRelationships($model, $relationshipPath);
                } catch (\Exception $e) {
                    // Log the error and skip this column
                    \Log::warning("Invalid relationship path: {$columnPath}. Error: " . $e->getMessage());
                    continue;
                }
            }
        }

        $model->where(function ($query) use ($searchColumns, $searchValue) {
            foreach ((array)$searchColumns as $columnPath) {
                $this->applyNestedConditions($query, $columnPath, $searchValue);
            }
        });
    }


    /**
     * Recursively apply conditions for nested relationships to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *   The query builder instance.
     *
     * @param string $column
     *   The column or relationship path (e.g., "relationship.column").
     *
     * @param string $value
     *   The value to search for.
     */

  
     private function applyNestedConditions($query, $columnPath, $searchValue)
     {
         // Handle direct column search
         if (strpos($columnPath, '.') === false) {
             $query->orWhere($columnPath, 'like', "%{$searchValue}%");
             return;
         }
     
         // Split the path into relationships and target column
         $parts = explode('.', $columnPath);
         $targetColumn = array_pop($parts);
         
         // Build relationship chain for nested queries
         $currentRelation = array_shift($parts);
         $relationPath = $currentRelation;
         
         $query->orWhereHas($currentRelation, function ($subQuery) use ($parts, $targetColumn, $searchValue, $relationPath) {
             if (empty($parts)) {
                 // If no more relations, search in the target column
                 $subQuery->where($targetColumn, 'like', "%{$searchValue}%");
                 return;
             }
     
             // Handle remaining nested relationships
             $this->buildNestedRelationQuery($subQuery, $parts, $targetColumn, $searchValue, $relationPath);
         });
    }
     

    private function buildNestedRelationQuery($query, $relations, $targetColumn, $searchValue, $relationPath)
    {
        $currentRelation = array_shift($relations);
        $newPath = $relationPath . '.' . $currentRelation;

        if (empty($relations)) {
            // Last level of nesting
            $query->whereHas($currentRelation, function ($q) use ($targetColumn, $searchValue) {
                $q->where($targetColumn, 'like', "%{$searchValue}%");
            });
            return;
        }

        // Continue building nested relationship chain
        $query->whereHas($currentRelation, function ($subQuery) use ($relations, $targetColumn, $searchValue, $newPath) {
            $this->buildNestedRelationQuery($subQuery, $relations, $targetColumn, $searchValue, $newPath);
        });
    }
    
    // Helper method to validate relationship existence
    private function validateRelationships($model, $relationshipPath)
    {
        $relationships = explode('.', $relationshipPath);
        $currentModel = $model;

        foreach ($relationships as $relationship) {
            if (!method_exists($currentModel, $relationship)) {
                throw new \Exception("Relationship '{$relationship}' not found in model " . get_class($currentModel));
            }
            
            $relationType = new \ReflectionMethod($currentModel, $relationship);
            $returnType = $relationType->getReturnType();
            
            if (!$returnType || !is_a($returnType->getName(), 'Illuminate\Database\Eloquent\Relations\Relation', true)) {
                throw new \Exception("Method '{$relationship}' is not a valid relationship");
            }
            
            // Get the related model for next iteration
            $currentModel = $currentModel->{$relationship}()->getRelated();
        }
        
        return true;
    }

    /**
     * Apply ordering to the query based on the request parameters.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *   The query builder instance.
     */
    private function applyOrdering($query)
    {
        $orderBy = request()->input('orderBy');
        if ($orderBy) {
            $query->orderBy($orderBy['column'], $orderBy['direction']);
        }
    }

    /**
     * Apply pagination to the query based on the request parameters.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *   The query builder instance.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     *   The paginated result.
     */
    private function applyPagination($query)
    {
        return $query->paginate(
            request()->input('limit', 10), // Default limit of 10 if not provided
            ['*'], 
            'page', 
            request()->input('page', 1) // Default to page 1 if not provided
        );
    }
}



/*
    * Example Usage:
    */

/*
    * API Request Example:
    * {
    *     "model": "Customer",
    *     "resource": "CustomerResource",
    *     "column": ["name", "phone", "email", "orders.product.category.name"],
    *     "value": "Electronics"
    * }
    *
    * Controller Implementation:
    *
    * $searchColumns = ['unique_id' => $user->id, 'name' => $user->name];
    *
    * $helper = new DynamicSearchHelper(
    *     request: $request,
    *     searchColumns: $searchColumns,
    *     withPagination: true,
    *     queryMode: false
    * );
    *
    * $result = $helper->getDynamicSearchData();
    */
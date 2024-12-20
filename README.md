# Dynamic Search Function for Laravel: Handle Nested Relationships and Complex Queries

## Overview
Introducing a powerful and flexible dynamic search function for Laravel applications, designed to simplify complex queries with nested and recursive relationship conditions. This package provides an elegant solution to dynamically search across multiple models and their related data, offering developers the ability to handle various levels of relationships in a single, streamlined query.

## Class Overview

##### Namespace: ChandraHemant\DynamicSearch
##### Namespace: ChandraHemant\CommonUtils
##### Author: Hemant Kumar Chandra

## Features
* Dynamic Model and Resource Handling: Pass the model name and resource class directly through the API request, enabling easy and dynamic querying across different models.
* Search Multiple Columns: Search across multiple columns within a model or its related models by simply specifying the columns in the API request.
* Nested and Recursive Relationships: The function supports nested relationships, allowing you to search deep within related models and their sub-relations.
* Flexible and Extendable: Easily extend or modify the functionality to fit your specific application's needs, making it a versatile tool for any Laravel project.
* Secure and Validated: Built-in validation ensures that only valid models and resources are processed, reducing the risk of errors and improving application security.

## Usage Example
Here's an example API request to demonstrate how the function works:

```json
{
    "model": "Customer",
    "resource": "CustomerResource",
    "column": ["name", "phone", "email", "orders.product.category.name"],
    "value": "Electronics"
}
```

In this example, the function will:
* Search within the Customer model for matches in the name, phone, or email fields.
* Traverse the orders relationship to the product model, then further into the category model to search for matches in the name field.

## Installation

You can easily add this functionality to your Laravel project by installing the package via Composer:

```bash
composer require chandra-hemant/htkc-utils
```

# DynamicSearchHelper & CommonUtils Class

The `DynamicSearchHelper` & `CommonUtils` class provides a set of methods for retrieving and manipulating data from a database table, especially tailored for use with Laravel's Eloquent ORM. This guide outlines how to effectively utilize these methods to implement dynamic search functionality.

## Core Methods

### getCustomModelData
Retrieves data from an Eloquent model with dynamic conditions and relationships.

```php
public static function getCustomModelData(
    Model $eloquentModel,
    array $dynamicConditions = [],
    bool|string $isFirst = false,
    int $limit = 0
)
```

Parameters:
- `$eloquentModel`: Laravel Eloquent model instance
- `$dynamicConditions`: Array of query conditions
- `$isFirst`: Boolean/string to get first/last record
- `$limit`: Number of records to retrieve

Example usage:
```php
$conditions = [
    [
        'method' => 'whereHas',
        'relation' => 'orders',
        'args' => ['status', '=', 'completed']
    ]
];
$result = CommonUtils::getCustomModelData($customerModel, $conditions);
```

### uploadFiles
Handles file uploads with various configuration options.

```php
public static function uploadFiles(
    Request $request,
    string $file,
    string $path,
    array $options = []
)
```

Parameters:
- `$request`: Laravel request instance
- `$file`: File input name
- `$path`: Upload path
- `$options`: Configuration array including:
  - `prefix`: File name prefix
  - `ref_file`: Reference to old file
  - `disk`: Storage disk
  - `default_extension`: Default file extension
  - `isArray`: Return array of paths
  - `isApi`: API mode flag

Example usage:
```php
$options = [
    'prefix' => 'user_avatar',
    'disk' => 'public',
    'isArray' => false
];
$filePath = CommonUtils::uploadFiles($request, 'avatar', 'uploads/avatars', $options);
```

*** Note:  Define the Disk in `config/filesystems.php`. It points to the `public/uploads` directory and is configured as a public disk. Make sure Disk name should be same as pointing directory name:
```php
...

'uploads' => [
    'driver' => 'local',
    'root' => public_path('uploads'), // Files stored in public/uploads
    'url' => env('APP_URL') . '/uploads', // URL to access files
    'visibility' => 'public',
],

...
```

### alphaNumericGenerator
Generates alphanumeric sequences with customizable format.

```php
public static function alphaNumericGenerator(
    int $num,
    string $const = '',
    string $prefix_id = '',
    string $ref_id = '',
    string $prefix_char = ''
): string
```

Parameters:
- `$num`: Number of digits
- `$const`: Constant prefix
- `$prefix_id`: Additional prefix
- `$ref_id`: Reference ID
- `$prefix_char`: Prefix character

Example usage:
```php
$newId = CommonUtils::alphaNumericGenerator(
    4,
    'INV-',
    'Y22-',
    'INV-AA0001'
);
```

### dataSubmitRecursion
Recursively attempts to save model data with safety checks.

```php
public static function dataSubmitRecursion(Model $model): bool
```

Example usage:
```php
$saved = CommonUtils::dataSubmitRecursion($userModel);
```

### dataDeleteRecursion
Recursively attempts to delete model data with safety checks.

```php
public static function dataDeleteRecursion(Model $model): bool
```

Example usage:
```php
$deleted = CommonUtils::dataDeleteRecursion($userModel);
```

### sendMail
Sends emails with advanced configuration options.

```php
public static function sendMail($recipient, $mailable, array $options = []): bool
```

Parameters:
- `$recipient`: Email recipient
- `$mailable`: Laravel Mailable class
- `$options`: Configuration array including:
  - `cc`: CC recipients
  - `bcc`: BCC recipients
  - `replyTo`: Reply-to address
  - `attachments`: Array of attachments

Example usage:
```php
$options = [
    'cc' => ['admin@example.com'],
    'attachments' => [
        ['path' => 'invoices/latest.pdf', 'name' => 'Invoice.pdf']
    ]
];
$sent = CommonUtils::sendMail('user@example.com', new WelcomeMail(), $options);
```

### sendPushNotification
Handles send notification using firebase.

```php
public static function sendPushNotification(
    string $title,
    string $body,
    $fcm_token,
    array $config,
    array $additionalData = []
)
```

Parameters:
- `$title`: Notification title
- `$body`: Notification body
- `$fcm_token`: Single FCM token or an array of FCM tokens
- `$config`: Configuration options provided by the user:
  - `serverKey`: The FCM server key
  - `url`: The FCM endpoint URL (default: "https://fcm.googleapis.com/fcm/send")
  - `priority`: The priority of the notification (default: "high")
  - `android_channel_id`: The Android channel ID (default: "high_importance_channel")
- `$additionalData` Optional additional data to include in the payload

Example usage:
```php
    $title = "Hello";
    $body = "This is a test notification.";
    $fcm_token = "YOUR_FCM_DEVICE_TOKEN";

    $config = [
        'serverKey' => 'YOUR_SERVER_KEY_HERE',
        'url' => 'https://fcm.googleapis.com/fcm/send',
        'priority' => 'high',
        'android_channel_id' => 'high_importance_channel',
    ];

    $additionalData = [
        'custom_key' => 'custom_value',
    ];

    $response = CommonUtils::sendPushNotification($title, $body, $fcm_token, $config, $additionalData);

    if ($response['status']) {
        echo $response['message'];
    } else {
        echo "Error: " . $response['message'];
        print_r($response['response']);
    }
```

## Usage

### Retrieving Data

You can retrieve data from your database table using the `getDynamicSearchData` method.

```php
use Illuminate\Http\Request;

$searchColumns = [
    // Specify your searchable columns here
];

$helper = new DynamicSearchHelper(
    request: $request,
    searchColumns: $searchColumns,
    withPagination: true,
    queryMode: false,
    isApi: true
);

$result = $helper->getDynamicSearchData();
```

### Search Functionality

You can enable search functionality by providing columns and relationships to search in.

```php
// Define search value, columns, and relationships
$searchColumns = ['column1','relationshipMethod.column2','relationshipMethod1.relationshipMethod2.column3','relationshipMethod3.relationshipMethod4.relationshipMethod5.relationshipMethod6.column8'];
```

### Pagination

Pagination is applied automatically based on the request parameters.

## Constructor Parameters

* `$request` (Illuminate\Http\Request)
* `$searchColumns` (array): An array specifying columns to search in
* `$withPagination` (bool): Specifying paginate data or not
* `$queryMode` (bool): Specifying data from the provided Eloquent model
* `$isApi` (bool): Specifying data format

## Methods

`getDynamicSearchData(): Illuminate\Support\Collection`
Retrieve dynamic search data from the provided Eloquent model.

### Example

Here's an example of how you can utilize these methods in your controller:

```php
use Illuminate\Http\Request;
use ChandraHemant\HtkcUtils\DynamicSearchHelper;

class YourController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $helper = new DynamicSearchHelper(
            request: $request,
            searchColumns: ['unique_id' => $user->unique_id],
            withPagination: true,
            queryMode: false,
            isApi: false,
        );
    
        // Get the dynamic search data
        $data = $helper->getDynamicSearchData();

        return view('your_view', compact('data'));
    }
}
```

## Conclusion

This guide provides a comprehensive overview of how to use the `DynamicSearchHelper` class in your Laravel application. By following these instructions, you can easily implement dynamic search functionality and utilize various helper methods for common tasks.

## How to Use

1. *Installation*: Ensure that the DataTableHelper class is included in your Laravel project.
2. *Initialization*: Create an instance of the DataTableHelper class.
3. *Data Retrieval*: Call the `getDynamicSearchData` method with specified columns, relationship columns, and other parameters to retrieve custom paginated and filtered data.
5. *Customization*: Adjust the class according to your specific use case by modifying the methods or extending its functionality.

#### Note: 
Maintaining consistency between the searchColumns array and the actual columns in your model and its relationships is crucial for accurate search results in the DynamicSearchHelper class.

The `searchColumns` array specifies the fields and relationships to be searched within your models. Each element should correspond directly to the columns and relationships defined in your Eloquent models. For instance, if you specify `['orders.product.category.name']` in the `searchColumns`, it assumes that your model has the appropriate relationships `(orders, product, category)` set up correctly and that each relationship has the `name` field available for searching.

To ensure that the dynamic search functionality works seamlessly, double-check that:

* The column names and relationship methods used in `searchColumns` match those defined in your Eloquent models.
* Relationships are correctly defined in your models and are accessible for querying.
* The search functionality aligns with the structure and naming conventions of your database schema.

Inconsistent or incorrect `searchColumns` values may lead to unexpected results or errors, as the helper class will attempt to search across columns and relationships that may not exist or be properly defined.
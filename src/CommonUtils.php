<?php

namespace ChandraHemant\HtkcUtils;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class CommonUtils
{
    /**
     * Fetch custom model details based on dynamic conditions and optional configurations.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquentModel The model to query.
     * @param array $dynamicConditions Array of conditions to apply dynamically. Each condition should have:
     *  - 'method' (string): Query method (e.g., 'where', 'orderBy', 'select').
     *  - 'args' (array): Arguments for the method.
     *  - Optional: 'relation' (array|string): Relationships to apply (for 'whereHas' or 'with').
     *  - Optional: 'parentMethod' (string): Parent method for nested relationships (e.g., 'whereRelation').
     *  - Optional: 'childMethod' (string): Child method for nested relationships.
     * @param bool|string $isFirst Fetch type:
     *  - `false` (default): Fetch all matching records.
     *  - `true`: Fetch the first matching record.
     *  - `'last'`: Fetch the last matching record.
     * @param int $limit Limit the number of records fetched.
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null Fetched result(s).
     */
    public static function getCustomModelData(
        Model $eloquentModel,
        array $dynamicConditions = [],
        bool|string $isFirst = false,
        int $limit = 0
    ) {
        // Start a new query
        $query = $eloquentModel->newQuery();

        // Apply dynamic conditions
        foreach ($dynamicConditions as $condition) {
            $method = $condition['method'];
            $args = $condition['args'];

            // Replace callable arguments with their return values
            if (is_callable(end($args))) {
                $args[key($args)] = end($args)($query);
            }

            // Handle specific methods with relations
            if ($method === 'select' && isset($condition['relation'])) {
                // Load specified relationships
                $query->with($condition['relation']);
                $query->select(...$args);
            } elseif ($method === 'whereHas' && isset($condition['relation'])) {
                $query->whereHas($condition['relation'], function ($query1) use ($args) {
                    $query1->where(...$args);
                });
            } elseif ($method === 'whereRelation' && isset($condition['relation'])) {
                $query->{$condition['parentMethod']}($condition['relation'], function ($query1) use ($args, $condition) {
                    $query1->{$condition['childMethod']}(...$args);
                });
            } else {
                // Apply other dynamic query methods
                $query->{$method}(...$args);
            }
        }

        // Apply limit if specified
        if ($limit > 0) {
            $query->limit($limit);
        }

        // Fetch results based on the isFirst flag
        if ($isFirst === 'last') {
            $result = $query->get()->last();
        } elseif ($isFirst) {
            $result = $query->first();
        } else {
            $result = $query->get();
        }

        return $result;
    }

    /**
     * Handles file uploads with support for video, audio, and other file types.
     *
     * @param \Illuminate\Http\Request $request The request object containing files.
     * @param string $file The name of the file input in the request.
     * @param string $path The path to store the uploaded files.
     * @param array $options Options to customize the upload process:
     *  - 'prefix' (string): Prefix for the uploaded file name.
     *  - 'ref_file' (string|array): Reference file(s) to delete after new uploads.
     *  - 'disk' (string): The storage disk to use (default: 'uploads').
     *  - 'default_extension' (string): Default file extension (default: 'png').
     *  - 'isArray' (bool): Whether to return multiple file paths as an array (default: true).
     *  - 'isApi' (bool): Whether the function is being used in an API context (default: false).
     *
     * @return array|string The uploaded file paths, either as a string or an array.
     */
    public static function uploadFiles(Request $request, string $file, string $path, array $options = [])
    {
        $prefix = $options['prefix'] ?? '';
        $ref_file = $options['ref_file'] ?? '';
        $disk = $options['disk'] ?? 'uploads';
        $defaultExtension = $options['default_extension'] ?? 'png';
        $isArray = $options['isArray'] ?? true;
        $isApi = $options['isApi'] ?? false;

        $file_path = $isArray ? [] : '';

        if ($request->hasFile($file)) {
            $files = is_array($request->file($file)) ? $request->file($file) : [$request->file($file)];

            foreach ($files as $new) {
                // Get extension or fallback to default
                $ext = $new->getClientOriginalExtension() ?: $defaultExtension;

                // Generate a unique name for the file
                $uniqueName = ($prefix ? $prefix . '_' : '') . uniqid() . mt_rand(0, 999999999) . '.' . $ext;

                // Handle storing files
                if (in_array($ext, ['mp4', 'avi', 'mkv', 'mp3', 'wav'])) {
                    // Move video/audio files
                    $storedFile = $new->move($disk . '/' . $path, $uniqueName);
                } else {
                    // Use storage for other files
                    $storedFile = $new->storeAs($path, $uniqueName, ['disk' => $disk]);
                }

                $fileEntry = $disk . '/' . $storedFile;
                if ($isArray) {
                    $file_path[] = $fileEntry;
                } else {
                    $file_path = $fileEntry;
                }
            }

            // Handle deletion of old files
            if (!empty($ref_file)) {
                self::deleteOldFiles($ref_file);
            }
        } else {
            if (!$isApi) {
                return header('Location: ' . $_SERVER['HTTP_REFERER']);
            }
        }

        return $file_path;
    }

    /**
     * Deletes old reference files.
     *
     * @param mixed $ref_file The file(s) to delete (string, array, or JSON-encoded array).
     */
    private static function deleteOldFiles($ref_file)
    {
        if (is_array($ref_file)) {
            foreach ($ref_file as $pf) {
                if (file_exists($pf)) {
                    unlink($pf);
                }
            }
        } elseif (json_decode($ref_file)) {
            foreach (json_decode($ref_file) as $pf) {
                if (file_exists($pf)) {
                    unlink($pf);
                }
            }
        } else {
            if (file_exists($ref_file)) {
                unlink($ref_file);
            }
        }
    }

    /**
     * Generate an alphanumeric string based on the given parameters.
     *
     * @param int $num The length of the numeric part of the result.
     * @param string $const A constant string to prepend to the result (default: '').
     * @param string $prefix_id An optional prefix ID to include in the result (default: '').
     * @param string $ref_id A reference ID to increment, if provided (default: '').
     * @param string $prefix_char A prefix character to include if the reference ID is empty (default: '').
     *
     * @return string The generated alphanumeric string.
     */
    public static function alphaNumericGenerator(
        int $num,
        string $const = '',
        string $prefix_id = '',
        string $ref_id = '',
        string $prefix_char = ''
    ): string {
        if($ref_id!='' || $ref_id!=null || !isEmpty($ref_id)){
            if($const != '')
                $ref_id = explode($const,$ref_id)[1];
            $match = preg_split('/([A-Za-z]+)/', $ref_id, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $match[1] = substr($match[1],-$num);
            if($match[1]!=str_repeat(9,$num)){
                $match[1]++;
            }else{
                $match[0]++;
                $match[1] = 1;
            }
            $result = $const.$match[0].$prefix_id.str_pad($match[1],$num,0,STR_PAD_LEFT);
        }else{
            if($prefix_char==''){
                $result = $const.'AA'.$prefix_id.str_pad(1,$num,0,STR_PAD_LEFT);
            }else{
                $result = $const.$prefix_char.$prefix_id.str_pad(1,$num,0,STR_PAD_LEFT);
            }
        }

        return $result;
    }

    /**
     * Save a model recursively until the save operation succeeds.
     *
     * @param \Illuminate\Database\Eloquent\Model $model The model instance to save.
     * 
     * @return bool True if the model is saved successfully.
     * 
     * @throws \Exception If maximum recursion depth is reached.
     */
    public static function dataSubmitRecursion(Model $model): bool
    {
        static $attempt = 0; // Track the number of attempts to prevent infinite recursion.
        $maxAttempts = 10; // Define a reasonable maximum number of attempts.

        if ($model->save()) {
            return true;
        }

        $attempt++;

        if ($attempt > $maxAttempts) {
            throw new \Exception('Maximum recursion depth exceeded while trying to save the model.');
        }

        return self::dataSubmitRecursion($model);
    }

    /**
     * Save a model recursively until the save operation succeeds.
     *
     * @param \Illuminate\Database\Eloquent\Model $model The model instance to save.
     * 
     * @return bool True if the model is saved successfully.
     * 
     * @throws \Exception If maximum recursion depth is reached.
     */
    public static function dataDeleteRecursion(Model $model): bool
    {
        static $attempt = 0; // Track the number of attempts to prevent infinite recursion.
        $maxAttempts = 10; // Define a reasonable maximum number of attempts.

        if ($model->delete()) {
            return true;
        }

        $attempt++;

        if ($attempt > $maxAttempts) {
            throw new \Exception('Maximum recursion depth exceeded while trying to save the model.');
        }

        return self::dataDeleteRecursion($model);
    }

    /**
     * Send an email using a mailable class.
     *
     * @param string|array $recipient Single email or an array of email addresses.
     * @param \Illuminate\Mail\Mailable $mailable The mailable instance to be sent.
     * @param array $options Optional settings:
     *  - 'cc' (array|string): Carbon copy recipients.
     *  - 'bcc' (array|string): Blind carbon copy recipients.
     *  - 'replyTo' (array|string): Reply-to email addresses.
     *  - 'attachments' (array): Attachments with ['path' => 'file_path', 'name' => 'optional_name'].
     * @return bool Indicates whether the email was sent successfully.
     */
    public static function sendMail($recipient, $mailable, array $options = []): bool
    {
        try {
            $mail = Mail::to($recipient);

            // Add optional CC, BCC, and Reply-To addresses
            if (!empty($options['cc'])) {
                $mail->cc($options['cc']);
            }
            if (!empty($options['bcc'])) {
                $mail->bcc($options['bcc']);
            }
            if (!empty($options['replyTo'])) {
                $mail->replyTo($options['replyTo']);
            }

            // Add attachments if provided
            if (!empty($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    $mail->attach($attachment['path'], [
                        'as' => $attachment['name'] ?? null,
                    ]);
                }
            }

            $mail->send($mailable);
            return true;
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Mail sending failed: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * Send a push notification via Firebase Cloud Messaging (FCM).
     *
     * @param string $title Notification title.
     * @param string $body Notification body.
     * @param string|array $fcm_token Single FCM token or an array of FCM tokens.
     * @param array $config Configuration options provided by the user.
     *   - 'serverKey' (string): The FCM server key.
     *   - 'url' (string): The FCM endpoint URL (default: "https://fcm.googleapis.com/fcm/send").
     *   - 'priority' (string): The priority of the notification (default: "high").
     *   - 'android_channel_id' (string): The Android channel ID (default: "high_importance_channel").
     * @param array $additionalData Optional additional data to include in the payload.
     * @return array Response containing the status and message.
     */
    public static function sendPushNotification(
        string $title,
        string $body,
        $fcm_token,
        array $config,
        array $additionalData = []
    ): array {
        // Extract configuration with defaults
        $url = $config['url'] ?? 'https://fcm.googleapis.com/fcm/send';
        $serverKey = $config['serverKey'] ?? '';
        $priority = $config['priority'] ?? 'high';
        $android_channel_id = $config['android_channel_id'] ?? 'high_importance_channel';

        if (empty($serverKey)) {
            return [
                'status' => false,
                'message' => 'FCM server key is required.',
            ];
        }

        // Prepare the notification payload
        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => '1',
            'android_channel_id' => $android_channel_id,
        ];

        // Prepare the data payload
        $data = array_merge(
            [
                'title' => $title,
                'description' => $body,
                'text' => $body,
                'is_read' => 0,
            ],
            $additionalData
        );

        // Prepare the complete payload
        $arrayToSend = [
            'to' => is_array($fcm_token) ? null : $fcm_token,
            'registration_ids' => is_array($fcm_token) ? $fcm_token : null,
            'notification' => $notification,
            'priority' => $priority,
            'data' => $data,
        ];

        // Remove empty keys
        $arrayToSend = array_filter($arrayToSend);

        $json = json_encode($arrayToSend);

        // Set headers
        $headers = [
            'Content-Type: application/json',
            'Authorization: key=' . $serverKey,
        ];

        // Initialize CURL and send the request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Return the result as an array
        if ($httpCode === 200) {
            return [
                'status' => true,
                'message' => 'Notification sent successfully.',
                'response' => json_decode($result, true),
            ];
        } else {
            return [
                'status' => false,
                'message' => 'Failed to send notification.',
                'response' => json_decode($result, true),
            ];
        }
    }


}
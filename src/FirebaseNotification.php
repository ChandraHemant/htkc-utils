<?php

namespace ChandraHemant\HtkcUtils;

use Google\Auth\CredentialsLoader;
use GuzzleHttp\Client;

class FirebaseNotification
{
    /**
     * Send a push notification via Firebase Cloud Messaging (FCM) using the FCM v1 API.
     *
     * @param string $title Notification title.
     * @param string $body Notification body.
     * @param string|array $fcm_token Single FCM token or an array of FCM tokens.
     * @param string $serviceAccountPath Path to the Firebase service account JSON file for authentication.
     * @param array $config Configuration options provided by the user.
     *   - 'url' (string): The FCM endpoint URL (default: "https://fcm.googleapis.com/v1/projects/YOUR_PROJECT_ID/messages:send").
     *   - 'android_channel_id' (string): The Android channel ID (default: "high_importance_channel").
     * @param array $additionalData Optional additional data to include in the payload.
     *   - Key-value pairs to include with the notification, such as 'user_id' => '123'.
     * @return array Response containing the status and message.
     *   - 'status' (bool): Whether the notification was successfully sent.
     *   - 'message' (string): A message describing the result.
     *   - 'response' (array, optional): The response from the FCM server, if available.
     */
    public static function sendPushNotification(
        string $title,
        string $body,
        $fcm_token,
        string $serviceAccountPath,
        array $config = [],
        array $additionalData = []
    ): array {
        try{
        // Prepare the credentials
        $credentials = CredentialsLoader::makeCredentials(
            ['https://www.googleapis.com/auth/firebase.messaging'],
            json_decode(file_get_contents($serviceAccountPath), true)
        );

        $authToken = $credentials->fetchAuthToken();
        $accessToken = $authToken['access_token'] ?? null;

        if (!$accessToken) {
            return [
                'status' => false,
                'message' => 'Failed to fetch OAuth token.',
            ];
        }

        // Basic notification payload
        $notification = [
            'title' => $title,
            'body' => $body,
        ];

        // Process image if provided
        if (!empty($config['image_url'])) {
            $notification['image'] = $config['image_url'];
        }

        // Base data payload
        $data = array_merge(
            [
                'title' => (string)$title,
                'description' => (string)$body,
                'text' => (string)$body,
                'is_read' => '0',
            ],
            array_map('strval', $additionalData)
        );

        // Android specific configuration
        $androidConfig = [
            'notification' => [
                'channel_id' => $config['android_channel_id'] ?? 'high_importance_channel',
                'notification_priority' => $config['priority'] ?? 'PRIORITY_HIGH',
                'default_sound' => $config['with_sound'] ?? true,
                'default_vibrate_timings' => true,
                'default_light_settings' => true,
            ],
            'priority' => 'high',
        ];

        // Add Android notification sound if specified
        if (!empty($config['custom_sound'])) {
            $androidConfig['notification']['sound'] = $config['custom_sound'];
        }

        // Add Android icon if specified
        if (!empty($config['android_icon'])) {
            $androidConfig['notification']['icon'] = $config['android_icon'];
        }

        // Add Android color if specified
        if (!empty($config['color'])) {
            $androidConfig['notification']['color'] = $config['color'];
        }

        // Add Android light settings if specified
        if (!empty($config['led_color']) || !empty($config['led_on_ms']) || !empty($config['led_off_ms'])) {
            $androidConfig['notification']['light_settings'] = [
                'color' => [
                    'red' => hexdec(substr($config['led_color'] ?? '#FFFFFF', 1, 2)) / 255,
                    'green' => hexdec(substr($config['led_color'] ?? '#FFFFFF', 3, 2)) / 255,
                    'blue' => hexdec(substr($config['led_color'] ?? '#FFFFFF', 5, 2)) / 255,
                    'alpha' => 1.0,
                ],
                'light_on_duration' => $config['led_on_ms'] ?? '200ms',
                'light_off_duration' => $config['led_off_ms'] ?? '200ms',
            ];
        }

        // Add Android actions if specified
        if (!empty($config['actions'])) {
            $androidConfig['notification']['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
            $actions = [];
            foreach ($config['actions'] as $action) {
                $actions[] = [
                    'action' => $action['id'],
                    'title' => $action['title'],
                    'icon' => $action['icon_path'] ?? null,
                ];
            }
            $androidConfig['notification']['actions'] = $actions;
        }

        // iOS (APNS) specific configuration
        $apnsConfig = [
            'payload' => [
                'aps' => [
                    'sound' => $config['with_sound'] ?? true ? 'default' : null,
                    'badge' => 1,
                    'content-available' => 1,
                ],
            ],
            'headers' => [
                'apns-priority' => '10',
            ],
        ];

        // Add iOS category if actions are specified
        if (!empty($config['actions'])) {
            $apnsConfig['payload']['aps']['category'] = $config['category_id'] ?? 'default_category';
        }

        // Build the final payload
        $messagePayload = [
            'message' => [
                'token' => is_array($fcm_token) ? null : $fcm_token,
                'notification' => $notification,
                'android' => $androidConfig,
                'apns' => $apnsConfig,
                'data' => $data,
            ],
        ];

        // Add tokens array if multiple tokens provided
        if (is_array($fcm_token)) {
            unset($messagePayload['message']['token']);
            $messagePayload['message']['tokens'] = $fcm_token;
        }

        // Send the request
        $client = new Client();
        $response = $client->post(
            $config['url'] ?? 'https://fcm.googleapis.com/v1/projects/YOUR_PROJECT_ID/messages:send',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $messagePayload,
            ]
        );

        $httpCode = $response->getStatusCode();

        if ($httpCode === 200) {
            return [
                'status' => true,
                'message' => 'Notification sent successfully.',
                'response' => json_decode($response->getBody(), true),
            ];
        }

        return [
            'status' => false,
            'message' => 'Failed to send notification.',
            'response' => json_decode($response->getBody(), true),
        ];

        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'Error sending notification: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send a push notification via Firebase Cloud Messaging (FCM) using Server Key.
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
    public static function sendPushNotificationWithServerKey(
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

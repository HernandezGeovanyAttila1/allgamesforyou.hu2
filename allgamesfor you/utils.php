<?php
/**
 * Compresses an image using the Tinify API.
 * 
 * @param string $filePath The absolute path to the image file.
 * @return bool True on success, false on failure.
 */
function compressImageWithTinyPng($filePath)
{
    $apiKey = 'gBpYLfK8dwDCHcM8kxffdhyNkkSSJCVD';

    if (!file_exists($filePath)) {
        return false;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.tinify.com/shrink",
        CURLOPT_USERPWD => "api:" . $apiKey,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => file_get_contents($filePath),
        CURLOPT_HTTPHEADER => ["Content-Type: application/octet-stream"],
        CURLOPT_SSL_VERIFYPEER => false, // Fix for local SSL issues
    ]);

    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $curl_error = curl_error($ch);

    if ($info['http_code'] === 201) {
        $result = json_decode($response, true);
        $compressedUrl = $result['output']['url'];

        // Download the compressed image and overwrite the original
        $ch_download = curl_init();
        curl_setopt_array($ch_download, [
            CURLOPT_URL => $compressedUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // Fix for local SSL issues
        ]);

        $compressedData = curl_exec($ch_download);
        if ($compressedData !== false) {
            file_put_contents($filePath, $compressedData);
            curl_close($ch_download);
            curl_close($ch);
            return true;
        }
        $dl_error = curl_error($ch_download);
        file_put_contents("debug.log", date("[Y-m-d H:i:s] ") . "Tinify Download Failed: $dl_error\n", FILE_APPEND);
        curl_close($ch_download);
    } else {
        file_put_contents("debug.log", date("[Y-m-d H:i:s] ") . "Tinify Shrink Failed: HTTP " . $info['http_code'] . " CURL: $curl_error Response: $response\n", FILE_APPEND);
    }

    curl_close($ch);
    return false;
}
?>
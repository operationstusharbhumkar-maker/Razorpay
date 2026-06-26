<?php
// supabase.php

require 'config.php';

function supabaseInsert($table, $data) {
    $url = SUPABASE_URL . '/rest/v1/' . $table;
    
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY,
            'Prefer: return=minimal'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        die("Connection Error: " . $error);
    }
    
    if ($httpCode === 201) {
        return ['success' => true];
    } else {
        die("Insert Failed (HTTP $httpCode): " . $response);
    }
}

function supabaseUpdate($table, $data, $column, $value) {
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?' . $column . '=eq.' . urlencode($value);
    
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY,
            'Prefer: return=minimal'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        die("Connection Error: " . $error);
    }
    
    if ($httpCode === 204 || $httpCode === 200) {
        return ['success' => true];
    } else {
        die("Update Failed (HTTP $httpCode): " . $response);
    }
}
?>
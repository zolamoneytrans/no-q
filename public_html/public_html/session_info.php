<?php
echo "Session save path: " . session_save_path() . "<br>";
if (is_writable(session_save_path())) {
    echo "The session save path is writable.<br>";
} else {
    echo "The session save path is NOT writable.<br>";
}
echo "Current session ID: " . session_id() . "<br>";
session_start();
echo "After session_start(), session data: ";
var_dump($_SESSION);
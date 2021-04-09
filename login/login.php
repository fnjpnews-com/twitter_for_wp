<?php
header("Location: " . "/wp-admin/admin.php?page=twitter_for_wp%2Ftwitter_for_wp.php&oauth_verifier=" . $_GET['oauth_verifier'] . "&oauth_token=" . $_GET['oauth_token']);
exit;

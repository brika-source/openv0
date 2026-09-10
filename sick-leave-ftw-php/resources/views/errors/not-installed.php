<?php
/** Shown when the database has not been created yet. */
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup required</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<main class="page-narrow-sm">
  <div class="card">
    <h3>Setup required</h3>
    <p>The database has not been created yet. Run the installer once from the project root:</p>
    <pre>php bin/console.php install</pre>
    <p class="muted">That creates the schema and loads the demo data (eleven accounts, eight sick leave
    cases, two fit-to-work assessments), then prints the demo sign-in details. Reload this page
    afterwards.</p>
    <hr class="divider">
    <p class="muted">Using MySQL or PostgreSQL instead of the default SQLite file? Set
    <code>DB_DRIVER</code>, <code>DB_HOST</code>, <code>DB_DATABASE</code>,
    <code>DB_USERNAME</code> and <code>DB_PASSWORD</code> in the environment first — see
    <code>config/config.php</code>.</p>
  </div>
</main>
</body>
</html>

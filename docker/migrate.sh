#!/bin/bash
# Database Migration Script
# Uses PHP PDO to handle schema initialization

echo "=== Running Database Migrations ==="

# Check if required environment variables are set
if [ -z "$DB_HOST" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ] || [ -z "$DB_DATABASE" ]; then
    echo "❌ Error: Required database environment variables are not set"
    echo "Required: DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE"
    exit 1
fi

# Run migrations using PHP
php -r "
    \$host = getenv('DB_HOST');
    \$user = getenv('DB_USERNAME');
    \$pass = getenv('DB_PASSWORD');
    \$db = getenv('DB_DATABASE');

    if (!\$host || !\$user || !\$pass || !\$db) {
        echo \"❌ Error: Required database environment variables are not set\n\";
        echo \"Required: DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE\n\";
        exit(1);
    }

    try {
        \$pdo = new PDO(\"mysql:host=\$host\", \$user, \$pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);

        // Ensure database exists
        \$pdo->exec(\"CREATE DATABASE IF NOT EXISTS \`\$db\`\");
        \$pdo->exec(\"USE \`\$db\`\");

        // Check if users table exists. If not, load schema.sql
        \$stmt = \$pdo->query(\"SHOW TABLES LIKE 'users'\");
        if (\$stmt->rowCount() == 0) {
            echo \"Users table not found. Initializing schema from schema.sql...\n\";
            \$schemaPath = '/var/www/html/database/schema.sql';
            if (file_exists(\$schemaPath)) {
                \$sql = file_get_contents(\$schemaPath);
                \$pdo->exec(\$sql);
                echo \"✓ Database schema initialized.\n\";
            } else {
                echo \"⚠ schema.sql not found at \$schemaPath\n\";
            }
        } else {
            echo \"✓ Database tables already exist. Skipping schema initialization.\n\";
        }

        // Ensure owner_id column exists (backward compatibility)
        \$stmt = \$pdo->prepare(\"
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'owner_id'
        \");
        \$stmt->execute([\$db]);

        if (\$stmt->fetchColumn() == 0) {
            echo \"Adding owner_id column to users table...\n\";
            \$pdo->exec('ALTER TABLE users ADD COLUMN owner_id INT UNSIGNED DEFAULT NULL');
            echo \"✓ owner_id column added.\n\";
        }

        // Ensure foreign key exists
        \$stmt = \$pdo->prepare(\"
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_owner_id'
        \");
        \$stmt->execute([\$db]);

        if (\$stmt->fetchColumn() == 0) {
            echo \"Adding foreign key constraint fk_owner_id...\n\";
            try {
                \$pdo->exec('ALTER TABLE users ADD CONSTRAINT fk_owner_id FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL');
                echo \"✓ Foreign key added.\n\";
            } catch (Exception \$ex) {
                echo \"⚠ Could not add foreign key (might exist): \" . \$ex->getMessage() . \"\n\";
            }
        }

        echo \"=== Migrations Completed Successfully ===\n\";

    } catch (Exception \$e) {
        echo \"❌ Migration ERROR: \" . \$e->getMessage() . \"\n\";
        exit(1);
    }
"

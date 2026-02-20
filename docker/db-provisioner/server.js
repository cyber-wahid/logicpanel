const express = require('express');
const helmet = require('helmet');
const mysql = require('mysql2/promise');
const { Client: PgClient } = require('pg');
const { MongoClient } = require('mongodb');
const crypto = require('crypto');

const app = express();
const PORT = process.env.PORT || 3001;
const API_SECRET = process.env.API_SECRET;

// Middleware
app.use(helmet());
app.use(express.json());

// Authentication middleware
const authenticate = (req, res, next) => {
    const authHeader = req.headers.authorization;
    if (!authHeader || authHeader !== `Bearer ${API_SECRET}`) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    next();
};

// Generate strong random password (Hex for URL safety)
const generatePassword = (length = 32) => {
    return crypto.randomBytes(length / 2).toString('hex'); // hex creates 2 chars per byte
};

// Generate short random suffix for database names (alphanumeric only)
const generateSuffix = (length = 6) => {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    const bytes = crypto.randomBytes(length);
    for (let i = 0; i < length; i++) {
        result += chars[bytes[i] % chars.length];
    }
    return result;
};

// MySQL Database Creation
app.post('/internal/db/mysql/create', authenticate, async (req, res) => {
    const { userId, dbId } = req.body;

    if (!userId || !dbId) {
        return res.status(400).json({ error: 'userId and dbId are required' });
    }

    const suffix = generateSuffix();
    const dbName = `u${userId}_d${dbId}_${suffix}`;
    const dbUser = `u${userId}_d${dbId}_${suffix}`;
    const dbPassword = generatePassword();

    try {
        // Use connection pool for better management
        const connection = await mysql.createConnection({
            host: process.env.MYSQL_HOST,
            port: Number(process.env.MYSQL_PORT), // Ensure integer
            user: 'root',
            password: process.env.MYSQL_ROOT_PASSWORD,
            connectTimeout: 20000, // 20s timeout
            multipleStatements: true
        });

        // Create database
        await connection.query(`CREATE DATABASE IF NOT EXISTS \`${dbName}\``);

        // Create user
        await connection.query(
            `CREATE USER IF NOT EXISTS '${dbUser}'@'%' IDENTIFIED BY '${dbPassword}'`
        );

        // Grant privileges (restricted to this DB only)
        await connection.query(
            `GRANT ALL PRIVILEGES ON \`${dbName}\`.* TO '${dbUser}'@'%'`
        );

        await connection.query('FLUSH PRIVILEGES');
        await connection.end();

        res.json({
            success: true,
            database: {
                name: dbName,
                user: dbUser,
                password: dbPassword,
                host: process.env.MYSQL_HOST,
                port: parseInt(process.env.MYSQL_PORT)
            }
        });

        console.log(`[MySQL] Created database: ${dbName} for user: ${dbUser}`);
    } catch (error) {
        console.error('[MySQL] Error creating database:', error);
        res.status(500).json({ error: 'Failed to create MySQL database', details: error.message });
    }
});

// MySQL Database Deletion
app.delete('/internal/db/mysql/:userId/:dbId', authenticate, async (req, res) => {
    const { userId, dbId } = req.params;
    const { dbName, dbUser } = req.body;

    if (!dbName || !dbUser) {
        return res.status(400).json({ error: 'dbName and dbUser are required in the request body' });
    }

    try {
        const connection = await mysql.createConnection({
            host: process.env.MYSQL_HOST,
            port: Number(process.env.MYSQL_PORT), // Ensure integer
            user: 'root',
            password: process.env.MYSQL_ROOT_PASSWORD,
            connectTimeout: 20000
        });

        await connection.query(`DROP DATABASE IF EXISTS \`${dbName}\``);
        await connection.query(`DROP USER IF EXISTS '${dbUser}'@'%'`);
        await connection.query('FLUSH PRIVILEGES');
        await connection.end();

        res.json({ success: true, message: 'MySQL database deleted' });
        console.log(`[MySQL] Deleted database: ${dbName}`);
    } catch (error) {
        console.error('[MySQL] Error deleting database:', error);
        res.status(500).json({ error: 'Failed to delete MySQL database', details: error.message });
    }
});

// PostgreSQL Database Creation
app.post('/internal/db/postgresql/create', authenticate, async (req, res) => {
    const { userId, dbId } = req.body;

    if (!userId || !dbId) {
        return res.status(400).json({ error: 'userId and dbId are required' });
    }

    const suffix = generateSuffix();
    const dbName = `u${userId}_d${dbId}_${suffix}`;
    const dbUser = `u${userId}_d${dbId}_${suffix}`;
    const dbPassword = generatePassword();

    try {
        const client = new PgClient({
            host: process.env.POSTGRES_HOST,
            port: process.env.POSTGRES_PORT,
            user: 'postgres',
            password: process.env.POSTGRES_ROOT_PASSWORD,
            database: 'postgres'
        });

        await client.connect();

        // Create user
        await client.query(`CREATE USER ${dbUser} WITH PASSWORD '${dbPassword}'`);

        // Create database
        await client.query(`CREATE DATABASE ${dbName} OWNER ${dbUser}`);

        // Revoke public schema access
        await client.query(`REVOKE ALL ON DATABASE ${dbName} FROM PUBLIC`);
        await client.query(`GRANT ALL PRIVILEGES ON DATABASE ${dbName} TO ${dbUser}`);

        await client.end();

        res.json({
            success: true,
            database: {
                name: dbName,
                user: dbUser,
                password: dbPassword,
                host: process.env.POSTGRES_HOST,
                port: parseInt(process.env.POSTGRES_PORT)
            }
        });

        console.log(`[PostgreSQL] Created database: ${dbName} for user: ${dbUser}`);
    } catch (error) {
        console.error('[PostgreSQL] Error creating database:', error);
        res.status(500).json({ error: 'Failed to create PostgreSQL database', details: error.message });
    }
});

// PostgreSQL Database Deletion
app.delete('/internal/db/postgresql/:userId/:dbId', authenticate, async (req, res) => {
    const { userId, dbId } = req.params;
    const { dbName, dbUser } = req.body;

    if (!dbName || !dbUser) {
        return res.status(400).json({ error: 'dbName and dbUser are required in the request body' });
    }

    try {
        const client = new PgClient({
            host: process.env.POSTGRES_HOST,
            port: process.env.POSTGRES_PORT,
            user: 'postgres',
            password: process.env.POSTGRES_ROOT_PASSWORD,
            database: 'postgres'
        });

        await client.connect();

        // Terminate existing connections
        await client.query(`
            SELECT pg_terminate_backend(pg_stat_activity.pid)
            FROM pg_stat_activity
            WHERE pg_stat_activity.datname = '${dbName}'
            AND pid <> pg_backend_pid()
        `);

        await client.query(`DROP DATABASE IF EXISTS ${dbName}`);
        await client.query(`DROP USER IF EXISTS ${dbUser}`);
        await client.end();

        res.json({ success: true, message: 'PostgreSQL database deleted' });
        console.log(`[PostgreSQL] Deleted database: ${dbName}`);
    } catch (error) {
        console.error('[PostgreSQL] Error deleting database:', error);
        res.status(500).json({ error: 'Failed to delete PostgreSQL database', details: error.message });
    }
});

// MongoDB Database Creation
app.post('/internal/db/mongodb/create', authenticate, async (req, res) => {
    const { userId, dbId } = req.body;

    if (!userId || !dbId) {
        return res.status(400).json({ error: 'userId and dbId are required' });
    }

    const suffix = generateSuffix();
    const dbName = `u${userId}_d${dbId}_${suffix}`;
    const dbUser = `u${userId}_d${dbId}_${suffix}`;
    const dbPassword = generatePassword();

    try {
        const uri = `mongodb://root:${process.env.MONGO_ROOT_PASSWORD}@${process.env.MONGO_HOST}:${process.env.MONGO_PORT}/admin`;
        const client = new MongoClient(uri);

        await client.connect();
        const adminDb = client.db('admin');

        // Create user with access to specific database only
        await adminDb.command({
            createUser: dbUser,
            pwd: dbPassword,
            roles: [
                { role: 'readWrite', db: dbName },
                { role: 'dbAdmin', db: dbName }
            ]
        });

        // Create the database by inserting a dummy document
        const userDb = client.db(dbName);
        await userDb.collection('_init').insertOne({ created: new Date() });

        await client.close();

        res.json({
            success: true,
            database: {
                name: dbName,
                user: dbUser,
                password: dbPassword,
                host: process.env.MONGO_HOST,
                port: parseInt(process.env.MONGO_PORT),
                authDatabase: dbName
            }
        });

        console.log(`[MongoDB] Created database: ${dbName} for user: ${dbUser}`);
    } catch (error) {
        console.error('[MongoDB] Error creating database:', error);
        res.status(500).json({ error: 'Failed to create MongoDB database', details: error.message });
    }
});

// MongoDB Database Deletion
app.delete('/internal/db/mongodb/:userId/:dbId', authenticate, async (req, res) => {
    const { userId, dbId } = req.params;
    const { dbName, dbUser } = req.body;

    if (!dbName || !dbUser) {
        return res.status(400).json({ error: 'dbName and dbUser are required in the request body' });
    }

    try {
        const uri = `mongodb://root:${process.env.MONGO_ROOT_PASSWORD}@${process.env.MONGO_HOST}:${process.env.MONGO_PORT}/admin`;
        const client = new MongoClient(uri);

        await client.connect();
        const adminDb = client.db('admin');

        // Drop database
        try {
            await client.db(dbName).dropDatabase();
        } catch (err) {
            console.log(`[MongoDB] Ignore dropDatabase error for ${dbName}:`, err.message);
        }

        // Drop user
        try {
            await adminDb.command({ dropUser: dbUser });
        } catch (err) {
            console.log(`[MongoDB] Ignore dropUser error for ${dbUser}:`, err.message);
        }

        await client.close();

        res.json({ success: true, message: 'MongoDB database deleted' });
        console.log(`[MongoDB] Deleted database: ${dbName}`);
    } catch (error) {
        console.error('[MongoDB] Error deleting database:', error);
        res.status(500).json({ error: 'Failed to delete MongoDB database', details: error.message });
    }
});

// Health check
app.get('/health', (req, res) => {
    res.json({ status: 'healthy', timestamp: new Date().toISOString() });
});

// Start server
app.listen(PORT, () => {
    console.log(`DB Provisioner Service running on port ${PORT}`);
    console.log(`MySQL: ${process.env.MYSQL_HOST}:${process.env.MYSQL_PORT}`);
    console.log(`PostgreSQL: ${process.env.POSTGRES_HOST}:${process.env.POSTGRES_PORT}`);
    console.log(`MongoDB: ${process.env.MONGO_HOST}:${process.env.MONGO_PORT}`);
});

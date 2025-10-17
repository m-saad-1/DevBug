// Fixed Code - server.js
const express = require('express');
const mysql = require('mysql2/promise'); // Using mysql2 with promises
const app = express();

// FIX: Use connection pool instead of creating connections per request
const pool = mysql.createPool({
    host: 'localhost',
    user: 'root',
    password: 'password',
    database: 'myapp',
    connectionLimit: 10, // Maximum number of connections in pool
    acquireTimeout: 60000, // 60 seconds timeout for acquiring connection
    timeout: 60000, // 60 seconds query timeout
    reconnect: true,
    queueLimit: 0 // Unlimited queuing
});

// Middleware for database connection handling
app.use(async (req, res, next) => {
    try {
        req.db = await pool.getConnection();
        next();
    } catch (error) {
        console.error('Database connection error:', error);
        res.status(500).json({ error: 'Database unavailable' });
    }
});

// Release connection back to pool after response
app.use((req, res, next) => {
    const originalSend = res.send;
    res.send = function(data) {
        if (req.db) {
            req.db.release(); // Return connection to pool
        }
        originalSend.call(this, data);
    };
    next();
});

// Error handling middleware for database connections
app.use((error, req, res, next) => {
    if (req.db) {
        req.db.release(); // Ensure connection is released on errors
    }
    console.error('Application error:', error);
    res.status(500).json({ error: 'Internal server error' });
});

// Fixed API endpoints
app.get('/api/users', async (req, res) => {
    try {
        const [rows] = await req.db.execute('SELECT id, username, email FROM users');
        res.json(rows);
    } catch (error) {
        console.error('Users query error:', error);
        res.status(500).json({ error: 'Failed to fetch users' });
    }
});

app.get('/api/posts', async (req, res) => {
    try {
        const [rows] = await req.db.execute(
            'SELECT id, title, content, created_at FROM posts LIMIT 100'
        );
        res.json(rows);
    } catch (error) {
        console.error('Posts query error:', error);
        res.status(500).json({ error: 'Failed to fetch posts' });
    }
});

// Health check endpoint to monitor pool status
app.get('/api/health', async (req, res) => {
    try {
        const poolStatus = {
            totalConnections: pool._allConnections.length,
            freeConnections: pool._freeConnections.length,
            acquiredConnections: pool._acquiredConnections.size
        };
        
        // Test database connection
        await pool.execute('SELECT 1');
        
        res.json({
            status: 'healthy',
            database: 'connected',
            pool: poolStatus,
            timestamp: new Date().toISOString()
        });
    } catch (error) {
        res.status(503).json({
            status: 'unhealthy',
            database: 'disconnected',
            error: error.message
        });
    }
});

// Graceful shutdown
process.on('SIGINT', async () => {
    console.log('Shutting down gracefully...');
    await pool.end();
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('Received SIGTERM, shutting down...');
    await pool.end();
    process.exit(0);
});

app.listen(3000, () => {
    console.log('Server running on port 3000 with connection pooling');
});
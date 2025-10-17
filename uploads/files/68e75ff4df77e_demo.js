// Buggy Code - server.js
const express = require('express');
const mysql = require('mysql');
const app = express();

// BUG: Connection created for each request - never closed
app.get('/api/users', (req, res) => {
    const connection = mysql.createConnection({
        host: 'localhost',
        user: 'root',
        password: 'password',
        database: 'myapp'
    });
    
    connection.connect();
    
    // BUG: Connection never closed
    connection.query('SELECT * FROM users', (error, results) => {
        if (error) {
            res.status(500).json({ error: 'Database error' });
            return;
        }
        res.json(results);
        // MISSING: connection.end();
    });
});

app.get('/api/posts', (req, res) => {
    const connection = mysql.createConnection({
        host: 'localhost',
        user: 'root', 
        password: 'password',
        database: 'myapp'
    });
    
    connection.connect();
    
    connection.query('SELECT * FROM posts LIMIT 100', (error, results) => {
        if (error) {
            res.status(500).json({ error: 'Database error' });
            return;
        }
        res.json(results);
        // MISSING: connection.end();
    });
});

app.listen(3000, () => {
    console.log('Server running on port 3000');
});
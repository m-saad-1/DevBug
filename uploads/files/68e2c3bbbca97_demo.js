// Buggy Component - UserDashboard.js
import React, { useState, useEffect } from 'react';
import axios from 'axios';

const UserDashboard = () => {
    const [userData, setUserData] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        // BUG: Missing dependency array causes infinite re-renders
        setLoading(true);
        axios.get('/api/user/profile')
            .then(response => {
                setUserData(response.data);
                setLoading(false);
            })
            .catch(error => {
                console.error('Error fetching user data:', error);
                setLoading(false);
            });
    }); // Missing dependency array

    return (
        <div className="dashboard">
            {loading ? (
                <div>Loading user data...</div>
            ) : (
                <div>
                    <h1>Welcome, {userData?.name}</h1>
                    <p>Email: {userData?.email}</p>
                </div>
            )}
        </div>
    );
};

export default UserDashboard;
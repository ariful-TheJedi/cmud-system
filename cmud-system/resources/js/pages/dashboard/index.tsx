import React from 'react';
import UserCard from '../../components/UserCard';

type DashboardProps = {
    user?: {
        name: string;
    };
};

export default function Dashboard({ user }: DashboardProps) {
    return (
        <div className="p-6">
            <h1 className="mb-4 text-2xl font-bold">Dashboard</h1>
            {user ? <UserCard name={user.name} role="Administrator" /> : null}
        </div>
    );
}

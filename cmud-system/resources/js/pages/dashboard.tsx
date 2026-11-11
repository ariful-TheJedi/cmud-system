// resources/js/Pages/Dashboard.jsx
import React from 'react'
// 1. Import the child component
import UserCard from '../components/UserCard'

type DashboardProps = {
  user: {
    name: string
  }
}

export default function Dashboard({ user }: DashboardProps) {
  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-4">Dashboard</h1>

      {/* 2. Render the child component inside the JSX */}
      <UserCard name={user.name} role="Administrator" />

      {/* You can also render multiple instances with different data */}
      <UserCard name="Alex Johnson" role="Software Engineer" />
    </div>
  )
}

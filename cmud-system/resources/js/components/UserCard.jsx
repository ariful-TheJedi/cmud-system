import React from 'react'

export default function UserCard({ name, role }) {
  return (
    <div className="p-4 border rounded shadow-sm bg-white">
      <h3 className="font-bold text-lg">{name}</h3>
      <p className="text-gray-600">{role}</p>
    </div>
  )
}
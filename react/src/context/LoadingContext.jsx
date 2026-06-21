import { createContext, useContext, useState } from 'react'

const LoadingCtx = createContext(null)

export function LoadingProvider({ children }) {
  const [loading, setLoading] = useState(true)
  return (
    <LoadingCtx.Provider value={{ loading, setLoading }}>
      {children}
    </LoadingCtx.Provider>
  )
}

export function useLoading() {
  return useContext(LoadingCtx)
}

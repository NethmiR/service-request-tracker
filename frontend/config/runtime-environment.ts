// Tells which Node.js environment the frontend is running in.
// Security headers and the Content Security Policy differ between development and production.

export enum NodeEnvironment {
  Development = "development",
  Production = "production",
}

export const isProductionRuntime = process.env.NODE_ENV === NodeEnvironment.Production;

export const isDevelopmentRuntime = process.env.NODE_ENV === NodeEnvironment.Development;

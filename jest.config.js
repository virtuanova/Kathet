module.exports = {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/tests/frontend/setupTests.js'],
  moduleNameMapping: {
    '^@/(.*)$': '<rootDir>/resources/js/$1',
    '^@components/(.*)$': '<rootDir>/resources/js/components/$1',
    '^@utils/(.*)$': '<rootDir>/resources/js/utils/$1',
    '^@hooks/(.*)$': '<rootDir>/resources/js/hooks/$1',
    '^@services/(.*)$': '<rootDir>/resources/js/services/$1',
    '^@types/(.*)$': '<rootDir>/resources/js/types/$1',
  },
  transform: {
    '^.+\\.(js|jsx|ts|tsx)$': 'babel-jest',
  },
  moduleFileExtensions: ['js', 'jsx', 'ts', 'tsx'],
  testMatch: [
    '<rootDir>/tests/frontend/**/*.test.{js,jsx,ts,tsx}',
    '<rootDir>/tests/frontend/**/*.spec.{js,jsx,ts,tsx}',
  ],
  collectCoverage: true,
  collectCoverageFrom: [
    'resources/js/**/*.{js,jsx,ts,tsx}',
    '!resources/js/**/*.d.ts',
    '!resources/js/bootstrap.js',
    '!resources/js/app.js',
    '!**/node_modules/**',
  ],
  coverageDirectory: '<rootDir>/coverage/frontend',
  coverageReporters: ['html', 'text', 'lcov', 'json'],
  coverageThreshold: {
    global: {
      branches: 80,
      functions: 80,
      lines: 80,
      statements: 80,
    },
  },
  testTimeout: 10000,
  verbose: true,
  clearMocks: true,
  restoreMocks: true,
};
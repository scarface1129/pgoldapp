# PGoldApp - Cryptocurrency Trading Platform API

A REST API for a cryptocurrency trading platform where users can buy and sell digital assets (BTC, ETH, USDT) using Nigerian Naira (NGN).

## Table of Contents
- [Features](#features)
- [Tech Stack](#tech-stack)
- [System Architecture](#system-architecture)
- [Setup Instructions](#setup-instructions)
- [API Documentation](#api-documentation)
- [Fee Structure](#fee-structure)
- [CoinGecko Integration](#coingecko-integration)
- [Running Tests](#running-tests)
- [Design Decisions](#design-decisions)
- [Trade-offs & Assumptions](#trade-offs--assumptions)
- [Time Spent](#time-spent)

## Features

- **User Authentication**: Secure registration and login with Laravel Sanctum tokens
- **Wallet Management**: Naira wallet with deposit/withdrawal capabilities
- **Cryptocurrency Trading**: Buy and sell BTC, ETH, and USDT
- **Real-time Rates**: Live exchange rates from CoinGecko API
- **Transaction Tracking**: Complete history of all wallet and trade transactions
- **Fee System**: Configurable percentage-based fees on trades
- **Portfolio Management**: View holdings with current market values

## Tech Stack

- **Framework**: Laravel 12.x
- **PHP Version**: 8.2+
- **Database**: MySQL
- **Authentication**: Laravel Sanctum
- **HTTP Client**: Guzzle
- **Testing**: PHPUnit

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      API Layer                               │
│  ┌───────────┐  ┌──────────────┐  ┌───────────────────────┐ │
│  │   Auth    │  │    Wallet    │  │       Trading         │ │
│  │Controller │  │  Controller  │  │      Controller       │ │
│  └─────┬─────┘  └──────┬───────┘  └───────────┬───────────┘ │
└────────┼───────────────┼──────────────────────┼─────────────┘
         │               │                      │
┌────────▼───────────────▼──────────────────────▼─────────────┐
│                    Service Layer                             │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐ │
│  │ WalletService  │  │ TradingService │  │CoinGeckoService│ │
│  └────────┬───────┘  └────────┬───────┘  └────────┬───────┘ │
└───────────┼───────────────────┼───────────────────┼─────────┘
            │                   │                   │
┌───────────▼───────────────────▼───────────┐      │
│              Data Layer                    │      │
│  ┌──────┐ ┌────────┐ ┌───────┐ ┌───────┐ │      │
│  │ User │ │ Wallet │ │ Trade │ │Holding│ │      │
│  └──────┘ └────────┘ └───────┘ └───────┘ │      │
│  ┌──────────────────┐ ┌────────────────┐ │      │
│  │WalletTransaction │ │   FeeSetting   │ │      │
│  └──────────────────┘ └────────────────┘ │      │
└──────────────────────────────────────────┘      │
                                                   │
                                    ┌──────────────▼──────────┐
                                    │    CoinGecko API        │
                                    │   (External Service)    │
                                    └─────────────────────────┘
```

### Key Components

1. **Controllers**: Handle HTTP requests and responses
2. **Services**: Business logic layer
   - `WalletService`: Wallet operations (deposit, withdraw, balance)
   - `TradingService`: Trade execution with fee calculation
   - `CoinGeckoService`: External API integration with caching
3. **Models**: Data entities with relationships and business methods

## Setup Instructions

### Prerequisites
- PHP 8.2 or higher
- Composer
- MySQL 5.7+ or 8.0
- XAMPP (or any MySQL server)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd pgoldapp
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Update `.env` with your database credentials**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=pgoldapp
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Create the database**
   ```sql
   CREATE DATABASE pgoldapp;
   ```

6. **Run migrations and seeders**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

7. **Start the development server**
   ```bash
   php artisan serve
   ```

The API will be available at `http://localhost:8000`

### Test Users (from seeder)
| Email | Password | Initial Balance |
|-------|----------|-----------------|
| test@example.com | password123 | ₦500,000 |
| test2@example.com | password123 | ₦0 |

## API Documentation

Full API documentation is available in [docs/API.md](docs/API.md).

### Quick Reference

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/v1/auth/register` | POST | No | Register new user |
| `/api/v1/auth/login` | POST | No | Login user |
| `/api/v1/auth/logout` | POST | Yes | Logout user |
| `/api/v1/auth/profile` | GET | Yes | Get user profile |
| `/api/v1/wallet` | GET | Yes | Get wallet details |
| `/api/v1/wallet/deposit` | POST | Yes | Deposit funds |
| `/api/v1/wallet/withdraw` | POST | Yes | Withdraw funds |
| `/api/v1/wallet/transactions` | GET | Yes | Transaction history |
| `/api/v1/crypto/prices` | GET | No | Get all crypto prices |
| `/api/v1/crypto/supported` | GET | No | List supported cryptos |
| `/api/v1/trade/buy` | POST | Yes | Buy cryptocurrency |
| `/api/v1/trade/sell` | POST | Yes | Sell cryptocurrency |
| `/api/v1/trade/quote` | POST | Yes | Get trade quote |
| `/api/v1/trade/history` | GET | Yes | Trade history |
| `/api/v1/crypto/portfolio` | GET | Yes | Portfolio with values |

### Example: Complete Trading Flow

```bash
# 1. Register
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John","email":"john@example.com","password":"password123","password_confirmation":"password123"}'

# 2. Login (save the token)
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123"}'

# 3. Deposit funds
curl -X POST http://localhost:8000/api/v1/wallet/deposit \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"amount":100000}'

# 4. Get quote
curl -X POST http://localhost:8000/api/v1/trade/quote \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"type":"buy","symbol":"BTC","amount":50000}'

# 5. Buy crypto
curl -X POST http://localhost:8000/api/v1/trade/buy \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"symbol":"BTC","amount":50000}'

# 6. View portfolio
curl http://localhost:8000/api/v1/crypto/portfolio \
  -H "Authorization: Bearer {token}"
```

## Fee Structure

### How Fees Work

The platform charges a **1.5% fee** on both buy and sell transactions. This is how the platform generates revenue.

#### Buy Transaction
- User specifies NGN amount to spend
- Fee is deducted from the NGN amount first
- Remaining amount is used to purchase crypto

**Example:** User wants to spend ₦100,000 on BTC
```
Total Amount:     ₦100,000.00
Fee (1.5%):       ₦1,500.00
Amount for Crypto: ₦98,500.00
BTC Received:     0.00145926 BTC (at ₦67.5M/BTC)
```

#### Sell Transaction
- User specifies crypto amount to sell
- NGN value is calculated from market rate
- Fee is deducted from the NGN proceeds

**Example:** User sells 0.001 BTC
```
BTC Sold:         0.001 BTC
Market Value:     ₦67,500.00
Fee (1.5%):       ₦1,012.50
NGN Received:     ₦66,487.50
```

### Configuration
Fees are stored in the `fee_settings` table and can be adjusted:
- `buy_fee`: Fee percentage for purchases
- `sell_fee`: Fee percentage for sales
- `minimum_amount`: Minimum transaction amount (₦1,000)

## CoinGecko Integration

### Overview
The platform uses [CoinGecko's free API](https://www.coingecko.com/en/api) to fetch real-time cryptocurrency prices in NGN.

### Implementation Details

1. **Service Class**: `App\Services\CoinGeckoService`
2. **Caching**: Prices are cached for 60 seconds to:
   - Reduce API calls
   - Stay within rate limits
   - Improve response times
3. **Error Handling**: Graceful fallback when API is unavailable
4. **Rate Limiting**: Respects CoinGecko's free tier limits

### Configuration
```env
COINGECKO_BASE_URL=https://api.coingecko.com/api/v3
COINGECKO_CACHE_TTL=60
COINGECKO_TIMEOUT=10
```

### Supported Endpoints Used
- `GET /simple/price` - Fetch prices for specific coins

## Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/TradingTest.php

# Run specific test method
php artisan test --filter=test_user_can_buy_crypto
```

### Test Structure
- `tests/Feature/AuthenticationTest.php` - Auth flow tests
- `tests/Feature/WalletTest.php` - Wallet operation tests
- `tests/Feature/TradingTest.php` - Trading functionality tests

### Mocking CoinGecko in Tests
Tests mock the `CoinGeckoService` to avoid external API calls and ensure consistent test results.

## Design Decisions

### 1. Service Layer Architecture
**Decision**: Separated business logic into service classes.  
**Rationale**: Keeps controllers thin, improves testability, and allows reuse of business logic.

### 2. Database Transactions
**Decision**: All trades are wrapped in database transactions.  
**Rationale**: Ensures atomicity - either all operations succeed (wallet debit, crypto credit, trade record) or all fail.

### 3. Fee Calculation in Service
**Decision**: Fees are calculated in `TradingService`, not stored as constants.  
**Rationale**: Allows dynamic fee adjustment without code changes.

### 4. Reference Numbers
**Decision**: Unique references for trades (`TRD-xxx`) and transactions (`WTX-xxx`).  
**Rationale**: Easy identification and audit trail.

### 5. Price Caching
**Decision**: 60-second cache for CoinGecko prices.  
**Rationale**: Balances real-time accuracy with API rate limits.

### 6. Polymorphic Relations
**Decision**: Wallet transactions use polymorphic `sourceable` relation to trades.  
**Rationale**: Enables flexible linking to different source types.

## Trade-offs & Assumptions

### Trade-offs Made

1. **Synchronous Trading**: Trades execute synchronously rather than using queues.
   - *Pros*: Simpler implementation, immediate user feedback
   - *Cons*: Longer response times if CoinGecko is slow

2. **Simple Authentication**: Using Sanctum tokens over OAuth.
   - *Pros*: Faster implementation, sufficient for API use
   - *Cons*: Less sophisticated than OAuth for multi-service auth

3. **Manual Deposit/Withdraw**: Simulated rather than actual payment integration.
   - *Pros*: Allows full testing of trading flow
   - *Cons*: Not production-ready for real payments

### Assumptions Made

1. **Single Currency**: Platform only supports NGN for fiat
2. **No Order Book**: Instant execution at market price (no limit orders)
3. **Trusted User Input**: Basic validation only (production would need more)
4. **No KYC**: User verification not implemented
5. **No 2FA**: Single-factor authentication only

### What I Would Add Given More Time

1. **Queue-based Trading**: Async trade processing for reliability
2. **Rate Limiting**: API rate limiting per user
3. **Webhook/Push Notifications**: For trade status updates
4. **Admin Panel**: Fee management, user management
5. **More Cryptos**: Dynamic support for additional cryptocurrencies
6. **Price Alerts**: Notify users when prices hit targets
7. **Comprehensive Logging**: Detailed audit logs
8. **API Versioning**: More robust versioning strategy

## Time Spent

| Task | Time |
|------|------|
| Project Setup & Configuration | 30 min |
| Database Design & Migrations | 45 min |
| Models & Relationships | 30 min |
| Authentication System | 30 min |
| Wallet Service & Controller | 45 min |
| CoinGecko Integration | 30 min |
| Trading Service & Controller | 1 hr |
| Tests | 45 min |
| Documentation | 45 min |
| **Total** | **~6 hours** |

## License

This project is created as part of a technical assessment and is not licensed for production use.

---

**Author**: Technical Assessment Submission  
**Date**: 2024

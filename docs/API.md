# PGoldApp API Documentation

## Base URL
```
http://localhost:8000/api/v1
```

## Authentication
The API uses **Bearer Token** authentication via Laravel Sanctum. Include the token in the Authorization header:
```
Authorization: Bearer {your_token}
```

---

## Endpoints

### Authentication

#### Register
Create a new user account.

**POST** `/auth/register`

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response (201):**
```json
{
    "success": true,
    "message": "Registration successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "created_at": "2024-01-15T10:30:00.000000Z"
        },
        "token": "1|abc123xyz...",
        "token_type": "Bearer"
    }
}
```

---

#### Login
Authenticate an existing user.

**POST** `/auth/login`

**Request Body:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "created_at": "2024-01-15T10:30:00.000000Z"
        },
        "token": "2|def456uvw...",
        "token_type": "Bearer"
    }
}
```

---

#### Logout
Revoke the current access token.

**POST** `/auth/logout`  
🔒 *Requires Authentication*

**Response (200):**
```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

---

#### Get Profile
Get the authenticated user's profile.

**GET** `/auth/profile`  
🔒 *Requires Authentication*

**Response (200):**
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "created_at": "2024-01-15T10:30:00.000000Z"
        },
        "wallet": {
            "balance": 500000.00,
            "currency": "NGN"
        }
    }
}
```

---

### Wallet

#### Get Wallet
Get the user's wallet details.

**GET** `/wallet`  
🔒 *Requires Authentication*

**Response (200):**
```json
{
    "success": true,
    "data": {
        "wallet": {
            "id": 1,
            "balance": 500000.00,
            "formatted_balance": "₦500,000.00",
            "currency": "NGN",
            "is_active": true
        }
    }
}
```

---

#### Deposit
Deposit funds into the wallet (for testing).

**POST** `/wallet/deposit`  
🔒 *Requires Authentication*

**Request Body:**
```json
{
    "amount": 100000
}
```

**Validation:**
- `amount`: Required, numeric, min: 100, max: 10,000,000

**Response (201):**
```json
{
    "success": true,
    "message": "Deposit successful",
    "data": {
        "transaction": {
            "reference": "WTX-65A8B2C3D4-1705312200",
            "type": "credit",
            "amount": 100000.00,
            "balance_before": 500000.00,
            "balance_after": 600000.00,
            "description": "Manual deposit",
            "created_at": "2024-01-15T10:30:00.000000Z"
        },
        "wallet": {
            "balance": 600000.00,
            "formatted_balance": "₦600,000.00"
        }
    }
}
```

---

#### Withdraw
Withdraw funds from the wallet (for testing).

**POST** `/wallet/withdraw`  
🔒 *Requires Authentication*

**Request Body:**
```json
{
    "amount": 50000
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Withdrawal successful",
    "data": {
        "transaction": {
            "reference": "WTX-65A8B2C3D5-1705312300",
            "type": "debit",
            "amount": 50000.00,
            "balance_before": 600000.00,
            "balance_after": 550000.00,
            "description": "Manual withdrawal",
            "created_at": "2024-01-15T10:35:00.000000Z"
        },
        "wallet": {
            "balance": 550000.00,
            "formatted_balance": "₦550,000.00"
        }
    }
}
```

**Error Response (400):**
```json
{
    "success": false,
    "message": "Insufficient balance"
}
```

---

#### Wallet Transactions
Get wallet transaction history with filtering and pagination.

**GET** `/wallet/transactions`  
🔒 *Requires Authentication*

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| type | string | Filter by type: `credit` or `debit` |
| source | string | Filter by source: `deposit`, `withdrawal`, `trade_buy`, `trade_sell` |
| date_from | date | Filter from date (YYYY-MM-DD) |
| date_to | date | Filter to date (YYYY-MM-DD) |
| per_page | integer | Results per page (1-100, default: 15) |

**Response (200):**
```json
{
    "success": true,
    "data": {
        "transactions": [
            {
                "id": 1,
                "reference": "WTX-65A8B2C3D4-1705312200",
                "type": "credit",
                "amount": 100000.00,
                "balance_before": 500000.00,
                "balance_after": 600000.00,
                "description": "Manual deposit",
                "source": "deposit",
                "created_at": "2024-01-15T10:30:00.000000Z"
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 1,
            "per_page": 15,
            "total": 1
        }
    }
}
```

---

### Trading

#### Get Supported Cryptocurrencies
Get list of supported cryptocurrencies.

**GET** `/crypto/supported`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "supported_cryptocurrencies": [
            {
                "symbol": "BTC",
                "name": "Bitcoin"
            },
            {
                "symbol": "ETH",
                "name": "Ethereum"
            },
            {
                "symbol": "USDT",
                "name": "Tether"
            }
        ]
    }
}
```

---

#### Get Crypto Prices
Get current prices for all supported cryptocurrencies.

**GET** `/crypto/prices`

**Response (200):**
```json
{
    "success": true,
    "data": {
        "prices": [
            {
                "symbol": "BTC",
                "name": "Bitcoin",
                "coin_id": "bitcoin",
                "price_ngn": 67500000.00,
                "last_updated_at": 1705312200,
                "fetched_at": "2024-01-15T10:30:00.000000Z"
            },
            {
                "symbol": "ETH",
                "name": "Ethereum",
                "coin_id": "ethereum",
                "price_ngn": 3750000.00,
                "last_updated_at": 1705312200,
                "fetched_at": "2024-01-15T10:30:00.000000Z"
            },
            {
                "symbol": "USDT",
                "name": "Tether",
                "coin_id": "tether",
                "price_ngn": 1500.00,
                "last_updated_at": 1705312200,
                "fetched_at": "2024-01-15T10:30:00.000000Z"
            }
        ]
    }
}
```

---

#### Get Single Crypto Price
Get current price for a specific cryptocurrency.

**GET** `/crypto/prices/{symbol}`

**Path Parameters:**
- `symbol`: Crypto symbol (BTC, ETH, USDT)

**Response (200):**
```json
{
    "success": true,
    "data": {
        "price": {
            "symbol": "BTC",
            "coin_id": "bitcoin",
            "price_ngn": 67500000.00,
            "last_updated_at": 1705312200,
            "fetched_at": "2024-01-15T10:30:00.000000Z"
        }
    }
}
```

---

#### Get Trade Quote
Get a price quote before executing a trade.

**POST** `/trade/quote`  
🔒 *Requires Authentication*

**Request Body:**
```json
{
    "type": "buy",
    "symbol": "BTC",
    "amount": 100000
}
```

**Parameters:**
| Field | Type | Description |
|-------|------|-------------|
| type | string | Trade type: `buy` or `sell` |
| symbol | string | Crypto symbol: `BTC`, `ETH`, `USDT` |
| amount | number | For buy: NGN amount. For sell: crypto amount |

**Response (200):**
```json
{
    "success": true,
    "data": {
        "quote": {
            "type": "buy",
            "crypto_symbol": "BTC",
            "ngn_amount": 100000.00,
            "fee_percentage": 1.50,
            "fee_amount": 1500.00,
            "crypto_amount": 0.00145926,
            "rate": 67500000.00,
            "minimum_amount": 1000.00,
            "rate_data": {
                "symbol": "BTC",
                "coin_id": "bitcoin",
                "price_ngn": 67500000.00
            }
        },
        "note": "This is an estimate. Actual rates may vary at time of execution."
    }
}
```

---

#### Buy Cryptocurrency
Buy cryptocurrency using NGN from your wallet.

**POST** `/trade/buy`  
🔒 *Requires Authentication*

**Request Body:**
```json
{
    "symbol": "BTC",
    "amount": 100000
}
```

**Parameters:**
| Field | Type | Description |
|-------|------|-------------|
| symbol | string | Crypto to buy: `BTC`, `ETH`, `USDT` |
| amount | number | NGN amount to spend (min: ₦1,000) |

**Response (201):**
```json
{
    "success": true,
    "message": "Buy order completed successfully",
    "data": {
        "trade": {
            "reference": "TRD-65A8B2C3D4-1705312200",
            "type": "buy",
            "crypto_symbol": "BTC",
            "crypto_amount": 0.00145926,
            "rate": 67500000.00,
            "subtotal": 98500.00,
            "fee_percentage": 1.50,
            "fee_amount": 1500.00,
            "total_amount": 100000.00,
            "status": "completed",
            "created_at": "2024-01-15T10:30:00.000000Z",
            "formatted": {
                "rate": "₦67,500,000.00",
                "subtotal": "₦98,500.00",
                "fee_amount": "₦1,500.00",
                "total_amount": "₦100,000.00"
            }
        }
    }
}
```

**Error Responses:**

*Insufficient Balance (400):*
```json
{
    "success": false,
    "message": "Insufficient wallet balance"
}
```

*Below Minimum (422):*
```json
{
    "success": false,
    "message": "Minimum transaction amount is ₦1,000.00"
}
```

---

#### Sell Cryptocurrency
Sell cryptocurrency and receive NGN in your wallet.

**POST** `/trade/sell`  
🔒 *Requires Authentication*

**Request Body:**
```json
{
    "symbol": "BTC",
    "amount": 0.001
}
```

**Parameters:**
| Field | Type | Description |
|-------|------|-------------|
| symbol | string | Crypto to sell: `BTC`, `ETH`, `USDT` |
| amount | number | Crypto amount to sell |

**Response (201):**
```json
{
    "success": true,
    "message": "Sell order completed successfully",
    "data": {
        "trade": {
            "reference": "TRD-65A8B2C3D5-1705312300",
            "type": "sell",
            "crypto_symbol": "BTC",
            "crypto_amount": 0.001,
            "rate": 67500000.00,
            "subtotal": 67500.00,
            "fee_percentage": 1.50,
            "fee_amount": 1012.50,
            "total_amount": 66487.50,
            "status": "completed",
            "created_at": "2024-01-15T10:35:00.000000Z",
            "formatted": {
                "rate": "₦67,500,000.00",
                "subtotal": "₦67,500.00",
                "fee_amount": "₦1,012.50",
                "total_amount": "₦66,487.50"
            }
        }
    }
}
```

**Error Responses:**

*Insufficient Crypto Balance (400):*
```json
{
    "success": false,
    "message": "Insufficient BTC balance"
}
```

---

#### Trade History
Get trading history with filtering and pagination.

**GET** `/trade/history`  
🔒 *Requires Authentication*

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| type | string | Filter: `buy` or `sell` |
| symbol | string | Filter: `BTC`, `ETH`, `USDT` |
| status | string | Filter: `pending`, `completed`, `failed`, `cancelled` |
| date_from | date | Filter from date (YYYY-MM-DD) |
| date_to | date | Filter to date (YYYY-MM-DD) |
| per_page | integer | Results per page (1-100, default: 15) |

**Response (200):**
```json
{
    "success": true,
    "data": {
        "trades": [
            {
                "reference": "TRD-65A8B2C3D4-1705312200",
                "type": "buy",
                "crypto_symbol": "BTC",
                "crypto_amount": 0.00145926,
                "rate": 67500000.00,
                "subtotal": 98500.00,
                "fee_percentage": 1.50,
                "fee_amount": 1500.00,
                "total_amount": 100000.00,
                "status": "completed",
                "created_at": "2024-01-15T10:30:00.000000Z"
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 1,
            "per_page": 15,
            "total": 1
        }
    }
}
```

---

#### Get Trade Details
Get details of a specific trade by reference.

**GET** `/trade/{reference}`  
🔒 *Requires Authentication*

**Response (200):**
```json
{
    "success": true,
    "data": {
        "trade": {
            "reference": "TRD-65A8B2C3D4-1705312200",
            "type": "buy",
            "crypto_symbol": "BTC",
            "crypto_amount": 0.00145926,
            "rate": 67500000.00,
            "subtotal": 98500.00,
            "fee_percentage": 1.50,
            "fee_amount": 1500.00,
            "total_amount": 100000.00,
            "status": "completed",
            "created_at": "2024-01-15T10:30:00.000000Z"
        }
    }
}
```

---

### Portfolio

#### Get Holdings
Get user's cryptocurrency holdings.

**GET** `/crypto/holdings`  
🔒 *Requires Authentication*

**Response (200):**
```json
{
    "success": true,
    "data": {
        "holdings": [
            {
                "symbol": "BTC",
                "name": "Bitcoin",
                "balance": 0.00145926
            },
            {
                "symbol": "ETH",
                "name": "Ethereum",
                "balance": 0.05
            }
        ]
    }
}
```

---

#### Get Portfolio
Get portfolio with current market values.

**GET** `/crypto/portfolio`  
🔒 *Requires Authentication*

**Response (200):**
```json
{
    "success": true,
    "data": {
        "portfolio": {
            "holdings": [
                {
                    "symbol": "BTC",
                    "name": "Bitcoin",
                    "balance": 0.00145926,
                    "current_price_ngn": 67500000.00,
                    "value_ngn": 98500.05
                },
                {
                    "symbol": "ETH",
                    "name": "Ethereum",
                    "balance": 0.05,
                    "current_price_ngn": 3750000.00,
                    "value_ngn": 187500.00
                }
            ],
            "total_value_ngn": 286000.05
        }
    }
}
```

---

## Error Responses

### Standard Error Format
```json
{
    "success": false,
    "message": "Error description"
}
```

### Validation Error (422)
```json
{
    "message": "The email field is required.",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

### Unauthenticated (401)
```json
{
    "message": "Unauthenticated."
}
```

### Not Found (404)
```json
{
    "success": false,
    "message": "Trade not found"
}
```

### Service Unavailable (503)
```json
{
    "success": false,
    "message": "Unable to fetch prices. Please try again later."
}
```

---

## Rate Limiting
The CoinGecko free tier has rate limits. Prices are cached for 60 seconds to minimize API calls and improve performance.

## Notes
- All monetary amounts are in Nigerian Naira (NGN)
- Crypto amounts use 8 decimal places for precision
- All timestamps are in ISO 8601 format (UTC)

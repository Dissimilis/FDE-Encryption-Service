# FDE Encryption Service

## A Secure Key Management System for Full Disk Encryption

The FDE Encryption Service is a secure system for managing encryption keys needed to decrypt network storage devices (NAS) with full disk encryption. It provides a secure mechanism to deliver encryption keys to a booting system through an authenticated, one-time password process.

## How It Works

Think of this system as a secure lockbox with a one-time combination that only the admin knows. When your encrypted storage device boots up, it needs the key to unlock itself, but storing that key on the device would defeat the purpose of encryption. Instead:

1. **Device Boot Process**:
   - When a NAS boots up, it runs the Client script
   - The Client generates a random one-time password (OTP)
   - The Client sends this OTP to the admin via push notification
   - The Client begins periodically checking the server for the key

2. **Admin Authentication**:
   - Admin receives the push notification with the OTP
   - Admin clicks the link in the notification to access the web interface
   - Admin authenticates with username/password
   - Admin enters the OTP and the actual encryption password/key

3. **Secure Key Delivery**:
   - The server encrypts the password using both the system key and the OTP
   - When the Client checks again with the correct OTP, the server:
     - Verifies the authentication and OTP
     - Decrypts and delivers the key
     - Immediately deletes the stored encrypted key
     - Logs the successful key delivery

4. **Disk Decryption**:
   - The NAS receives the encryption key
   - The NAS uses the key to decrypt the disk
   - The OTP file is deleted to ensure it cannot be reused

## Components

### Client.php

This script runs on the NAS device during boot and handles:

- Random OTP generation (default 6 characters)
- Push notification delivery to admin via Pushover
- Polling the key server for the encryption key
- Receiving and logging the retrieved key

### index.php

This is the main server script that provides:

- The `/getkey` endpoint for securely delivering keys
- The `/enterkey` web interface for admins to enter keys
- Strong encryption using Argon2id for key derivation and AES-256-CBC
- Comprehensive logging and notifications 
- One-time-use key delivery

## Security Features

- **One-Time Passwords (OTP)**: Each boot-up generates a new unique OTP
- **Push Notifications**: Immediate admin alerts when a device requests a key
- **Argon2id Key Derivation**: Industry-standard password hashing for key derivation
- **AES-256-CBC Encryption**: Strong encryption for stored keys
- **Single-Use Keys**: Encrypted keys are deleted after being read once
- **Detailed Logging**: All activities are logged with timestamps and IP addresses
- **HTTPS Communication**: All communication should be over HTTPS (requires proper server configuration)

## Deployment

The service can be easily deployed using Docker:

```bash
docker-compose up -d
```

The service will be available at http://localhost:8087 (or https:// if properly configured with SSL).

## Environment Configuration

The following environment variables can be configured:

| Variable | Description | Default |
|----------|-------------|---------|
| USER_AUTH_PASSWORD | Admin login password | abc |
| CLIENT_AUTH_KEY | Authorization key for clients | xyz |
| AES_KEY | Key for AES encryption | xxx |
| AES_IV | Initialization vector for AES | d7575a8ffbce7bbc |
| LOG_FILE_PATH | Path to log file | log.txt |

## Security Considerations

- For production use, always change the default passwords and keys
- Configure proper SSL/TLS for the web service
- Consider implementing IP restrictions for additional security
- Regularly review logs for unauthorized access attempts
- This system should be deployed on a separate, secured server
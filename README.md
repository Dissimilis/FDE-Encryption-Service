# FDE Encryption Service

## A Secure Solution for Data Protection

The FDE Encryption Service is a reliable and secure system designed for managing encryption keys and safeguarding sensitive data. By leveraging a one-time password (OTP) mechanism, along with real-time notifications and detailed logging, this service ensures encryption and decryption are both robust and user-friendly.

## Overview

The service operates through two primary components:

- **Client.php**: Responsible for OTP generation, user notifications, and retrieving encryption keys from a secure endpoint.
- **Service.php**: Handles data encryption, decryption, and the management of encryption keys.

Together, these components provide a seamless and secure workflow for managing encryption and decryption tasks.

---

## Key Features

### Client.php

The **Client** script plays a crucial role in the encryption process by handling the following tasks:

1. **OTP Generation**: Creates a unique one-time password (OTP) for secure encryption key retrieval.
2. **User Notification**: Sends OTP notifications to users via Pushover, along with additional details.
3. **Encryption Key Retrieval**: Continuously checks a secure server endpoint, submitting the OTP to obtain the encryption key when available.
4. **Comprehensive Logging**: Records all activities with timestamped logs, ensuring transparency and traceability.

### Service.php

The **Service** script handles the core encryption and decryption processes, ensuring secure key management. Its main features include:

1. **Advanced Encryption and Decryption**: Uses robust encryption algorithms to protect data from unauthorized access.
2. **Key Management**: Generates, securely stores, and rotates encryption keys to maintain integrity.
3. **Error Handling**: Responds to invalid requests with clear error messages, ensuring security and user-friendliness.
4. **Logging and Notifications**: Tracks all key interactions and sends critical action alerts.

---

## How It Works

### Workflow

1. The **Client script** generates a one-time password (OTP) and notifies the user with relevant information.
2. The client monitors a secure server endpoint, submitting the OTP to retrieve the encryption key.
3. The **Service script** validates incoming requests and, if authorized, provides the requested encryption key.
4. The service performs encryption and decryption of data securely, with detailed logs capturing each step for accountability.

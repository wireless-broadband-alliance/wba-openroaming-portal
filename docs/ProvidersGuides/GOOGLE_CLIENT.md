# Google Authenticator Credentials

Follow these steps to obtain your **Google Client ID** and **Google Client Secret**:

---

## Step 1: Go to the Google Cloud Console
1. Open your browser and navigate to the [Google Cloud Console](https://console.cloud.google.com/).
2. **Sign In** with your Google account.

---

## Step 2: Create a New Project
1. Click the **Project Dropdown** (near the top-left corner).
2. Select **New Project**.
3. Provide a **Name** for your project (e.g., "OpenRoaming Provisioning Portal") and select an **Organization** (if applicable).
4. Click **Create**.

---

## Step 3: Enable the OAuth 2.0 API
1. In your new project, open the **Navigation Menu** (☰) on the top-left.
2. Navigate to **APIs & Services** > **Library**.
3. Search for **Google Identity Services API** or **OAuth 2.0**.
4. Click on the API and select **Enable**.

---

## Step 4: Configure the OAuth Consent Screen
1. Go to **APIs & Services** > **OAuth Consent Screen**.
2. Choose between **External** (for public apps) or **Internal** (for private apps, restricted to your organization).
    - For most web apps, select **External**.
3. Provide the required **Application Information**, such as:
    - **App Name**
    - **User Support Email**
    - **Developer Contact Information**
4. Save these details and proceed to **Scopes**—this is where you define the data your app will access.
    - Add any required scopes (e.g., email or profile).
5. Finish the consent screen configuration.

---

## Step 5: Create OAuth Client Credentials
1. Go to **APIs & Services** > **Credentials**.
2. Click on **Create Credentials** > **OAuth Client ID**.
3. Choose **Web Application** as the application type.
4. Provide a **Name** for the client (e.g., "App Client").
5. Add your application’s **Authorized Redirect URIs**:
    - Examples: `https://yourportaldomain.com/google/check` or `http://localhost:8000/google/check`
    - Ensure that this matches the URLs in your portal domain application.
6. Click **Create**.

---

## Step 6: Obtain the Client ID and Client Secret
Once the credentials are created, you will see:
- **Client ID**
- **Client Secret**

**Download or copy these values** for use in your portal. You won't see the secret again without regenerating it, so keep it secure.

---

## Step 7: Add the Values to Portal
In the root folder, look for the `.env` file, store the credentials like this:

```dotenv
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
```

These credentials will integrate into your portal login for Google Authentication or API calls.

#!/usr/bin/env python3
"""
Send welcome email with password fetched from SQL Server.
Usage: python userProfileMailSender.py <username>
Requirements: 11
    pip install pyodbc msal requests
"""
import sys
import pyodbc
import requests
import os
import json
import base64
from msal import ConfidentialClientApplication

# ---------------- CONFIG ----------------
SQL_SERVER = 'WIN-KAHC4Q7AUHG\SEBON'
SQL_DATABASE = 'RiskMatrix_AML'
SQL_USERNAME = 'sa'
SQL_PASSWORD = '$eb0N2026'

CLIENT_ID = os.getenv("OAUTH_CLIENT_ID", "45f45c02-2b3e-432d-a1fd-0980f50f6d59")
CLIENT_SECRET = os.getenv("OAUTH_CLIENT_SECRET", "")
TENANT_ID = os.getenv("OAUTH_TENANT_ID", "4ee6b0fa-3bdc-4fa2-8cd8-9ece2266c058")
SENDER_EMAIL = 'aml@sebon.gov.np'
SENDER_NAME = 'Risk Matrix System'

GRAPH_API_ENDPOINT = 'https://graph.microsoft.com/v1.0'
# ----------------------------------------

def get_sql_connection():
    conn_str = f'DRIVER={{ODBC Driver 17 for SQL Server}};SERVER={SQL_SERVER};DATABASE={SQL_DATABASE};UID={SQL_USERNAME};PWD={SQL_PASSWORD}'
    return pyodbc.connect(conn_str)

def get_user_credentials(username):
    conn = get_sql_connection()
    cursor = conn.cursor()
    query = """
        SELECT username, password_hash
        FROM dbo.Users_Detail_mp
        WHERE username = ?
    """
    cursor.execute(query, (username,))
    row = cursor.fetchone()
    conn.close()
    
    if row:
        return row[0], row[1]  # username, password
    return None, None

def get_access_token():
    app = ConfidentialClientApplication(
        CLIENT_ID,
        authority=f"https://login.microsoftonline.com/{TENANT_ID}",
        client_credential=CLIENT_SECRET
    )
    result = app.acquire_token_for_client(scopes=["https://graph.microsoft.com/.default"])
    
    if "access_token" in result:
        print(" Access token acquired successfully")
        
        # Decode token to check permissions
        try:
            token_parts = result['access_token'].split('.')
            payload = token_parts[1]
            payload += '=' * (4 - len(payload) % 4)
            decoded = base64.b64decode(payload)
            token_data = json.loads(decoded)
            
            print("\n Token Permissions (roles):")
            if 'roles' in token_data:
                for role in token_data['roles']:
                    print(f"  - {role}")
                if 'Mail.Send' not in token_data['roles']:
                    print("\n WARNING: 'Mail.Send' permission NOT found!")
                    print("   Emails may not be delivered.")
                    print("\n   Fix: Go to Azure Portal → App Registration → API Permissions")
                    print("        Add 'Mail.Send' application permission and grant admin consent")
            else:
                print("   No application permissions found in token!")
        except Exception as e:
            print(f"  (Could not decode token: {e})")
        
        print()
        return result['access_token']
    else:
        print(f" Failed to acquire access token: {result.get('error_description')}")
        sys.exit(1)

def send_email(to_email, password):
    access_token = get_access_token()
    
    # KEY FIX: Add "from" field in message like the working OTP script
    html_body = f"""
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {{ 
            font-family: Arial, sans-serif; 
            background-color: #f4f4f4; 
            padding: 20px; 
            margin: 0;
        }}
        .container {{ 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }}
        .header {{ 
            text-align: center; 
            color: #2563eb; 
            font-size: 24px; 
            margin-bottom: 20px; 
            font-weight: bold;
        }}
        .credentials-box {{ 
            background: #f0f7ff; 
            border: 2px solid #2563eb; 
            padding: 20px; 
            border-radius: 8px; 
            margin: 20px 0; 
        }}
        .cred-row {{
            margin: 10px 0;
            font-size: 14px;
        }}
        .cred-label {{
            font-weight: bold;
            color: #64748b;
        }}
        .cred-value {{
            color: #1e293b;
            font-family: 'Courier New', monospace;
            background: #fff;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
        }}
        .info {{ 
            color: #64748b; 
            font-size: 14px; 
            line-height: 1.6; 
            margin: 10px 0;
        }}
        .warning {{ 
            color: #ef4444; 
            font-size: 13px; 
            margin-top: 20px; 
            padding: 15px; 
            background: #fee; 
            border-radius: 5px; 
            border-left: 4px solid #ef4444;
        }}
        .footer {{ 
            text-align: center; 
            color: #94a3b8; 
            font-size: 12px; 
            margin-top: 30px; 
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">🔐 Welcome to SEBON Risk Matrix Calculation</div>
        
        <p class="info">Dear Reporting Entity,</p>
        <p class="info">Your user profile has been successfully created. Here are your login credentials:</p>
        
        <div class="credentials-box">
            <div class="cred-row">
                <div class="cred-label">Username:</div>
                <div class="cred-value">{to_email}</div>
            </div>
            <div class="cred-row">
                <div class="cred-label">Password:</div>
                <div class="cred-value">{password}</div>
            </div>
        </div>
        
        <p class="info">Please login to the system and change your password immediately after your first login.</p>
        
        <div class="warning">
            ⚠️ <strong>Security Notice:</strong><br><br>
            • Keep your credentials secure and confidential<br>
            • Change your password after first login<br>
            • Never share your password with anyone<br>
            • Contact support if you suspect unauthorized access
        </div>
        
        <div class="footer">
            <p><strong>Risk Matrix AML System</strong></p>
            <p>This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>
    """
    
    email_msg = {
        "message": {
            "subject": "Welcome to RiskMatrix AML - Your Login Credentials",
            "body": {
                "contentType": "HTML",
                "content": html_body
            },
            "toRecipients": [
                {
                    "emailAddress": {
                        "address": to_email
                    }
                }
            ],
            # KEY FIX: Add "from" field like the working script
            "from": {
                "emailAddress": {
                    "address": SENDER_EMAIL,
                    "name": SENDER_NAME
                }
            }
        },
        "saveToSentItems": "false"  # Match working script
    }
    
    headers = {
        'Authorization': f'Bearer {access_token}',
        'Content-Type': 'application/json'
    }
    
    endpoint = f"{GRAPH_API_ENDPOINT}/users/{SENDER_EMAIL}/sendMail"
    
    print(f"\nAttempting to send email:")
   
    
    response = requests.post(endpoint, json=email_msg, headers=headers, timeout=30)
    
    print(f"\nResponse Status: {response.status_code}")
    
    if response.status_code == 202:
        print(f"Email successfully queued for {to_email}")
        
        return True
    else:
        print(f" Failed to send email: {response.status_code}")
        try:
            error_detail = response.json()
            print(f"Error details: {json.dumps(error_detail, indent=2)}")
        except:
            print(f"Response text: {response.text}")
        return False

def main():
    if len(sys.argv) != 2:
        print("Usage: python userProfileMailSender.py <username>")
        sys.exit(1)
    
    username = sys.argv[1]
    
    
    
    email, password = get_user_credentials(username)
    
    if not email:
        print(f" No user found with username: {username}")
        sys.exit(1)
    
    print(f" User found: {email}")
    
    if '@' not in email:
        print(f" Invalid email address: {email}")
        sys.exit(1)
    
    success = send_email(email, password)
    
    print("\n" + "="*60)
    if success:
        print("Process completed successfully")
        sys.exit(0)
    else:
        print(" Process completed with errors")
        sys.exit(1)

if __name__ == "__main__":
    main()
# Security Policy

## Supported Versions
We actively maintain and support the latest stable release of **Auto-Internal Linker**. Please ensure you are using the latest version to benefit from security updates and improvements.

| Version       | Supported         |
|--------------|------------------|
| 1.x (latest) | ✅ Actively supported |
| Older versions | ❌ No longer supported |

## Reporting a Vulnerability
We take security seriously and appreciate your help in making **Auto-Internal Linker** more secure. If you discover a vulnerability, please follow the process below:

1. **Do not disclose publicly.** Please report security issues privately to prevent exploits.
2. **Send an email to [bojan.cvjetkovic@gmail.com]** with the subject "Security Vulnerability Report."
3. **Include the following details:**
   - Description of the vulnerability
   - Steps to reproduce the issue
   - Potential impact and severity assessment
   - Any suggested mitigation or fixes (if available)

We will acknowledge receipt of your report within 48 hours and work on a resolution as soon as possible.

## Security Best Practices
To ensure a secure experience, we follow these principles:
- **Input Validation & Sanitization**: We sanitize and escape user inputs using WordPress functions (`esc_html()`, `sanitize_text_field()`, etc.).
- **Nonces for Verification**: We use nonces to prevent CSRF attacks.
- **Role & Capability Checks**: We enforce proper permissions for administrative actions.
- **Secure Database Queries**: We use `$wpdb->prepare()` to prevent SQL injections.
- **Regular Code Reviews**: We conduct security audits and update dependencies.

## Responsible Disclosure
If a security issue is reported, we will:
1. Investigate and verify the vulnerability.
2. Develop and test a patch.
3. Release an update with security fixes.
4. Credit responsible disclosure contributors (if desired).

Your help in identifying and reporting vulnerabilities is greatly appreciated. Thank you for keeping **Auto-Internal Linker** secure! 🚀


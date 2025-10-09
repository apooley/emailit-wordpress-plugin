# Glossary

This glossary defines key terms and concepts used in Emailit Integration. Understanding these terms will help you better use the plugin and troubleshoot issues.

## A

**API (Application Programming Interface)**
- A set of rules and protocols that allows different software applications to communicate with each other. Emailit Integration uses APIs to send emails through Emailit's service.

**API Key**
- A unique identifier used to authenticate and authorize access to an API. Your Emailit API key allows the plugin to send emails through Emailit's service.

**API Endpoint**
- A specific URL where API requests are sent. The Emailit API endpoint is where the plugin sends email data for processing.

**Authentication**
- The process of verifying the identity of a user or system. Emailit Integration authenticates with Emailit's API using your API key.

## B

**Batch Size**
- The number of emails processed together in a single operation. Larger batch sizes can improve performance but require more server resources.

**Bounce**
- An email that cannot be delivered to the recipient's mailbox. Bounces can be hard (permanent) or soft (temporary).

**Bounce Classification**
- The process of categorizing bounces by type and reason. Emailit Integration automatically classifies bounces to help you understand delivery issues.

**Bounce Rate**
- The percentage of emails that bounce back. A high bounce rate can damage your sender reputation.

**Bounce Threshold**
- The number of soft bounces allowed before a subscriber is treated as a hard bounce. This helps distinguish between temporary and permanent delivery issues.

## C

**Circuit Breaker**
- A design pattern that prevents system overload by automatically stopping requests when failures exceed a threshold. Emailit Integration uses circuit breakers to protect against API outages.

**Complaint**
- When a recipient marks an email as spam. High complaint rates can damage your sender reputation and lead to delivery issues.

**Cron Job**
- A scheduled task that runs automatically at specified intervals. WordPress uses cron jobs to process email queues and perform maintenance tasks.

**CSRF (Cross-Site Request Forgery)**
- A type of attack where unauthorized commands are transmitted from a user that the website trusts. Emailit Integration includes CSRF protection to prevent such attacks.

## D

**DKIM (DomainKeys Identified Mail)**
- An email authentication method that uses digital signatures to verify that emails are sent from the claimed domain. DKIM helps improve email deliverability.

**DMARC (Domain-based Message Authentication, Reporting and Conformance)**
- An email authentication protocol that builds on SPF and DKIM to provide domain-level protection against email spoofing.

**DNS (Domain Name System)**
- The system that translates domain names into IP addresses. DNS records like SPF, DKIM, and DMARC are used for email authentication.

**Deliverability**
- The ability of emails to reach the recipient's inbox without being filtered as spam. Good deliverability depends on sender reputation, authentication, and content quality.

## E

**Email Queue**
- A system that stores emails temporarily and processes them in the background. This improves website performance by preventing timeouts during bulk email sending.

**Encryption**
- The process of converting data into a secure format that can only be read by authorized parties. Emailit Integration encrypts your API key for security.

**Error Analytics**
- The analysis of error patterns and trends to identify recurring issues and improve system reliability.

## F

**Fallback**
- A backup system that takes over when the primary system fails. Emailit Integration can fall back to WordPress's default wp_mail() function if the API fails.

**FluentCRM**
- A WordPress CRM plugin that can be integrated with Emailit Integration for advanced contact management and bounce handling.

**FluentCRM Integration**
- The connection between Emailit Integration and FluentCRM that allows automatic bounce handling and subscriber management.

## H

**Hard Bounce**
- A permanent email delivery failure, such as an invalid email address or non-existent domain. Hard bounces should result in immediate removal from your email list.

**HMAC (Hash-based Message Authentication Code)**
- A method for verifying the authenticity and integrity of webhook data. Emailit Integration uses HMAC to secure webhook communications.

**HTML Email**
- An email formatted with HTML markup that can include images, links, and styled text. Emailit Integration supports both HTML and plain text emails.

## I

**IP Address**
- A unique numerical identifier assigned to each device connected to the internet. IP addresses are used for routing data and can affect email deliverability.

**Index**
- A database structure that improves query performance by creating a sorted reference to data. Emailit Integration uses indexes to optimize database performance.

## L

**Log**
- A record of events, activities, or errors that occur in a system. Emailit Integration maintains detailed logs of email activity for monitoring and troubleshooting.

**Log Retention**
- The period of time that log data is kept before being automatically deleted. Configurable log retention helps manage database size.

## M

**Memory Management**
- The process of monitoring and optimizing memory usage to prevent performance issues and system crashes.

**Multisite**
- A WordPress configuration that allows multiple websites to be managed from a single WordPress installation. Emailit Integration supports WordPress multisite.

## P

**PHP**
- A programming language used to develop WordPress and its plugins. Emailit Integration requires PHP 8.0 or higher.

**Power User Mode**
- A feature that shows or hides advanced configuration options based on user preference. Each user can set their own power user mode preference.

**Progressive Disclosure**
- A design principle that shows information gradually, starting with basic options and revealing advanced features as needed.

## Q

**Queue Processing**
- The background processing of emails that have been queued for sending. This improves website performance by preventing timeouts.

**Query Optimization**
- The process of improving database query performance through indexing, efficient queries, and proper data structure.

## R

**Rate Limiting**
- The practice of limiting the number of requests or operations that can be performed within a specific time period. This prevents system overload and abuse.

**Retry Logic**
- A system that automatically retries failed operations with increasing delays between attempts. This improves reliability by handling temporary failures.

**Reverse DNS (rDNS)**
- The process of resolving an IP address back to a domain name. Proper reverse DNS setup can improve email deliverability.

## S

**Sender Reputation**
- A score that email providers assign to senders based on their sending practices. Good sender reputation improves email deliverability.

**Soft Bounce**
- A temporary email delivery failure, such as a full mailbox or temporarily unavailable server. Soft bounces may resolve themselves and don't require immediate action.

**SPF (Sender Policy Framework)**
- An email authentication method that specifies which servers are authorized to send emails for a domain. SPF helps prevent email spoofing.

**SQL Injection**
- A type of attack where malicious SQL code is inserted into input fields. Emailit Integration prevents SQL injection through parameterized queries.

## T

**Test Email**
- A feature that allows you to send a test email to verify that your configuration is working correctly.

**Timeout**
- The maximum amount of time to wait for a response before considering an operation failed. Configurable timeouts help prevent system hangs.

**Tooltip**
- A small popup that appears when hovering over an element, providing additional information or help.

## U

**Unsubscribe**
- The process of removing a recipient from an email list. Emailit Integration can automatically process unsubscribe requests through webhooks.

**User Role**
- A set of permissions that determine what a user can do in WordPress. Only users with appropriate roles can access Emailit Integration settings.

## V

**Validation**
- The process of checking data to ensure it meets certain criteria or requirements. Emailit Integration validates all input data for security and reliability.

**Version Control**
- The management of changes to software over time. Emailit Integration uses version control to track updates and changes.

## W

**Webhook**
- A method of real-time communication between applications. Emailit Integration uses webhooks to receive instant notifications about email events.

**Webhook Secret**
- A security key used to verify that webhook data comes from a trusted source. This prevents unauthorized webhook requests.

**WordPress Cron**
- WordPress's built-in task scheduler that runs background tasks at specified intervals. Emailit Integration uses WordPress cron for queue processing.

**wp_mail()**
- WordPress's default email function that the plugin replaces with Emailit's API service.

## X

**XSS (Cross-Site Scripting)**
- A type of attack where malicious scripts are injected into web pages. Emailit Integration prevents XSS attacks through output escaping and input sanitization.

## Y

**YAML**
- A human-readable data serialization format sometimes used for configuration files.

## Z

**Zero Configuration**
- The ability of software to work without manual configuration. While Emailit Integration requires basic setup, it works with minimal configuration once the API key is entered.

---

**Need more help understanding these terms?** Check the [User Guide](user-guide.md) for practical examples or the [FAQ](faq.md) for common questions about these concepts.


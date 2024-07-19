# Academic Opportunities Telegram Bot

This Telegram bot assists users in searching for academic opportunities, managing their profiles, and providing AI-assisted writing support for academic documents.

## Features

- Search for academic opportunities based on user profiles or custom criteria
- User profile creation and management
- AI-assisted writing for resumes, cover letters, and emails
- Supervisor search functionality
- Latest opportunities listing
- Help and support system

## Setup

1. Create a Telegram bot and obtain the API token from BotFather.
2. Replace the `$token` variable in the script with your bot's token.
3. Set up a webhook for your bot to point to this PHP script.
4. Ensure PHP is installed on your server with file writing permissions.
5. Create a channel for the bot and replace `$channelUsername` with your channel's username.

## Dependencies

- PHP 7.0 or higher
- PHP cURL extension

## File Structure

- `index.php`: Main bot script
- `user_data.json`: JSON file to store user profile data

## Usage

Users interact with the bot through Telegram. The bot provides a menu-based interface for various functions:

1. Search Opportunities
2. User Profile
3. AI Assistant
4. Help and Support
5. View/Edit Profile
6. Search Supervisors

## API Integration

The bot integrates with an external API (https://jet.aysan.dev/api_v2.html) for searching and retrieving academic opportunities.

## Security Considerations

- Ensure proper validation and sanitization of user inputs.
- Protect the `user_data.json` file from unauthorized access.
- Use HTTPS for the webhook to secure data transmission.

## Maintenance

Regular updates may be required to keep the bot functioning with the latest Telegram API changes and to improve features based on user feedback.


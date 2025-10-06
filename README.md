# Civic Management System - File Manager & Backup

A comprehensive file management and database backup system with cloud storage integration.

## Recent Updates

### File Manager Page (`/manage/pages/file_manager/`)
- Modern Google Drive-inspired interface with grid and list views
- Full-page drag-and-drop file upload zone
- Advanced file operations (preview, download, rename, move, delete)
- Real-time search and filtering
- Statistics dashboard
- R2 cloud storage integration
- Context menu with right-click actions
- Responsive mobile design

### Backup Page (`/manage/pages/backup/`)
- Modern dashboard-style interface
- Quick action cards for backup operations
- Real-time statistics (total backups, size, latest backup, R2 status)
- Advanced search and filtering
- One-click backup creation, restoration, and cleanup
- R2 cloud upload integration
- Beautiful gradient action cards
- Mobile responsive design

## System Requirements

### Directory Setup
```bash
mkdir -p /home/civicbd/civicgroup/storage
mkdir -p /home/civicbd/civicgroup/backups
chmod 755 /home/civicbd/civicgroup/storage
chmod 755 /home/civicbd/civicgroup/backups
```

### Cron Jobs
```bash
# Backup every 6 hours
0 */6 * * * /usr/bin/php /path/to/project/cron-backup.php >> /var/log/backup-cron.log 2>&1

# Process upload queue every 15 minutes
*/15 * * * * /usr/bin/php /path/to/project/cron-upload-queue.php >> /var/log/upload-queue.log 2>&1

# Cleanup old backups daily at 2 AM
0 2 * * * /usr/bin/php /path/to/project/cron-cleanup.php >> /var/log/cleanup.log 2>&1
```

## Key Improvements Needed

### 1. Environment Configuration
**File:** `.env`

**Current Issues:**
- Database credentials may need updating
- R2/S3 credentials need to be configured for cloud storage
- Backup retention policy settings

**Recommended Updates:**
```env
# Database Configuration
DB_HOST=localhost
DB_NAME=your_database
DB_USER=your_username
DB_PASSWORD=your_password

# Cloudflare R2 / S3 Configuration
R2_ENABLED=true
R2_ACCOUNT_ID=your_account_id
R2_ACCESS_KEY_ID=your_access_key
R2_SECRET_ACCESS_KEY=your_secret_key
R2_BUCKET=your_bucket_name
R2_ENDPOINT=https://your_account_id.r2.cloudflarestorage.com

# Backup Configuration
BACKUP_RETENTION_DAYS=30
BACKUP_DIR=/home/civicbd/civicgroup/backups
STORAGE_DIR=/home/civicbd/civicgroup/storage

# Auto Backup Secret (for cron jobs)
AUTO_BACKUP_SECRET=generate_random_secure_token_here
```

### 2. File Manager Helper
**File:** `assets/includes/file_manager_helper.php`

**Improvements Needed:**
- Add file type validation (MIME type checking)
- Implement file size limits per user role
- Add virus scanning integration (ClamAV)
- Implement file versioning system
- Add thumbnail generation for images
- Improve caching mechanism with Redis/Memcached

### 3. XHR API Handler
**File:** `xhr/file_manager.php`

**Improvements Needed:**
- Add rate limiting to prevent abuse
- Implement proper error logging
- Add API authentication tokens
- Implement batch operations for better performance
- Add webhook notifications for completed operations
- Improve error messages with detailed codes

### 4. Database Configuration
**File:** `assets/includes/tabels.php`

**Improvements Needed:**
- Add indexes on frequently queried columns
- Implement database connection pooling
- Add query performance monitoring
- Optimize table structure for file metadata
- Add full-text search indexes for file names

### 5. Security Enhancements

**Priority Updates:**
- [ ] Implement CSRF token validation on all POST requests
- [ ] Add file upload validation (magic bytes, not just extensions)
- [ ] Implement user quota enforcement in backend
- [ ] Add rate limiting on API endpoints
- [ ] Encrypt sensitive files at rest
- [ ] Add audit logging for all file operations
- [ ] Implement IP whitelisting for admin operations
- [ ] Add 2FA requirement for restore operations

### 6. Performance Optimizations

**Recommended Changes:**
- [ ] Implement lazy loading for large file lists
- [ ] Add CDN integration for static file delivery
- [ ] Implement chunked file uploads for large files (>100MB)
- [ ] Add background job queue for heavy operations
- [ ] Implement database query caching
- [ ] Add Redis for session management
- [ ] Optimize image thumbnails with WebP format
- [ ] Implement file compression before R2 upload

### 7. UI/UX Enhancements

**File Manager:**
- [ ] Add keyboard shortcuts (Ctrl+A for select all, Delete key, etc.)
- [ ] Implement file sharing with expiring links
- [ ] Add file preview for videos and audio files
- [ ] Implement collaborative features (comments, tags)
- [ ] Add bulk operations progress indicator
- [ ] Implement undo/redo functionality
- [ ] Add file activity timeline
- [ ] Implement advanced search with filters

**Backup System:**
- [ ] Add scheduled backup creation (recurring)
- [ ] Implement differential and incremental backups
- [ ] Add backup encryption option
- [ ] Implement backup testing/verification
- [ ] Add email notifications for backup status
- [ ] Create backup comparison tool
- [ ] Add restore preview (show what will be restored)
- [ ] Implement point-in-time recovery

### 8. Integration Improvements

**Cloud Storage:**
- [ ] Add support for multiple R2 buckets
- [ ] Implement AWS S3 as alternative
- [ ] Add Google Drive integration
- [ ] Implement Dropbox integration
- [ ] Add automatic failover between storage providers
- [ ] Implement cost optimization (lifecycle policies)

**Notifications:**
- [ ] Add Slack integration for backup notifications
- [ ] Implement email alerts for failed operations
- [ ] Add webhook support for custom integrations
- [ ] Implement SMS alerts for critical events

### 9. Monitoring and Logging

**Priority Additions:**
- [ ] Add application performance monitoring (APM)
- [ ] Implement centralized logging (ELK stack)
- [ ] Add storage usage analytics dashboard
- [ ] Implement backup success rate tracking
- [ ] Add real-time error alerting
- [ ] Create detailed audit trail
- [ ] Add user activity analytics

### 10. Testing and Quality

**Required Tests:**
- [ ] Unit tests for file operations
- [ ] Integration tests for R2 upload/download
- [ ] E2E tests for file manager workflows
- [ ] Load testing for concurrent uploads
- [ ] Security penetration testing
- [ ] Backup restoration testing (automated)

### 11. Documentation Improvements

**Missing Documentation:**
- [ ] API documentation (OpenAPI/Swagger)
- [ ] Admin user guide
- [ ] Deployment guide
- [ ] Troubleshooting guide
- [ ] Backup restoration procedures
- [ ] Disaster recovery plan
- [ ] Security best practices guide

### 12. Migration and Deployment

**Considerations:**
- [ ] Create database migration scripts
- [ ] Add rollback procedures
- [ ] Implement zero-downtime deployment
- [ ] Add configuration validation on startup
- [ ] Create health check endpoints
- [ ] Implement graceful shutdown handling

## Quick Start

### 1. Setup Environment
```bash
# Copy environment template
cp .env.example .env

# Edit configuration
nano .env

# Update paths in .env file
BACKUP_DIR=/home/civicbd/civicgroup/backups
STORAGE_DIR=/home/civicbd/civicgroup/storage
```

### 2. Create Required Directories
```bash
mkdir -p /home/civicbd/civicgroup/storage
mkdir -p /home/civicbd/civicgroup/backups
chmod 755 /home/civicbd/civicgroup/storage
chmod 755 /home/civicbd/civicgroup/backups
```

### 3. Setup Database
```bash
# Run migration
php -f migrations/001_file_manager_and_backup_system.sql
```

### 4. Configure Cron Jobs
```bash
crontab -e

# Add these lines:
0 */6 * * * /usr/bin/php /path/to/project/cron-backup.php >> /var/log/backup-cron.log 2>&1
*/15 * * * * /usr/bin/php /path/to/project/cron-upload-queue.php >> /var/log/upload-queue.log 2>&1
0 2 * * * /usr/bin/php /path/to/project/cron-cleanup.php >> /var/log/cleanup.log 2>&1
```

### 5. Test the System
```bash
# Test backup creation
php cron-backup.php

# Test upload queue processing
php cron-upload-queue.php

# Test cleanup
php cron-cleanup.php
```

## Architecture

### File Storage
- Local storage in `/storage` directory
- Cloud backup to Cloudflare R2 (S3-compatible)
- Automatic upload queue for large files
- Retention policy enforcement

### Backup System
- Automated full database backups
- Per-table backup support
- Compression using gzip
- Automatic cleanup based on retention days
- Point-in-time recovery capability

### API Endpoints
All endpoints are available at `xhr/file_manager.php?f=file_manager&s={action}`

Key actions:
- `list_local_folder` - List files and folders
- `upload_local` - Upload files
- `download_local` - Download files
- `delete_local` - Delete files/folders
- `create_folder` - Create new folder
- `upload_r2_from_local` - Upload to R2
- `create_full_backup` - Create database backup
- `restore_db_local` - Restore from backup

## Security Considerations

### Current Security Features
- Admin-only access to backup operations
- User authentication for file operations
- Path traversal prevention
- File type validation

### Security Improvements Needed
1. Add CSRF protection
2. Implement rate limiting
3. Add file virus scanning
4. Encrypt backups at rest
5. Add audit logging
6. Implement 2FA for sensitive operations

## Performance Considerations

### Current Performance Features
- File metadata caching
- Chunked file processing
- Background upload queue
- Optimized database queries

### Performance Improvements Needed
1. Implement Redis caching
2. Add CDN integration
3. Optimize large file handling
4. Add database connection pooling
5. Implement lazy loading

## Support and Maintenance

### Regular Maintenance Tasks
- Monitor backup success rates
- Check storage usage
- Review error logs
- Test backup restoration
- Update retention policies
- Monitor R2 costs

### Troubleshooting
See `FILE_MANAGER_AND_BACKUP_SYSTEM.md` for detailed troubleshooting guide.

## Version History

### v2.0.0 (Current)
- Complete UI overhaul with modern design
- Google Drive-inspired file manager
- Enhanced backup dashboard
- Improved mobile responsiveness
- Better search and filtering

### v1.0.0
- Initial file manager implementation
- Basic backup system
- R2 integration
- Cron job setup

## License

Proprietary - All rights reserved

## Contact

For issues and feature requests, contact the system administrator.

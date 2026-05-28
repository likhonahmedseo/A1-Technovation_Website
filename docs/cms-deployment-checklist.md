# A1 Technovation CMS Deployment Checklist

Use this checklist when deploying the PHP + MySQL CMS to `https://a1technovation.com/` through cPanel.

## 1. Upload Files

- Upload the website files to `public_html/`
- Make sure these CMS paths are included:
- `admin/`
- `php/cms/`
- `config/cms.php`
- `database/cms.sql`
- `blog.php`
- `blog-post.php`
- `public-page.php`
- `css/cms-public.css`
- `css/cms-pages.css`
- `assets/uploads/media/`
- `.htaccess`

## 2. Create MySQL Database

- Open `MySQL Database Wizard` in cPanel
- Create the CMS database
- Create the database user
- Add the user to the database
- Grant `ALL PRIVILEGES`

## 3. Import Database Schema

- Open `phpMyAdmin`
- Select the CMS database
- Import `database/cms.sql`
- Confirm these tables are created:
- `admin_users`
- `blog_posts`
- `media_files`
- `cms_pages`
- `cms_section_templates`
- `cms_settings`

## 4. Update CMS Config

Edit `config/cms.php` and confirm:

- `app.base_url` is `https://a1technovation.com`
- `database.host` is usually `localhost`
- `database.name` matches the cPanel database name
- `database.username` matches the cPanel database user
- `database.password` is correct

## 5. Check File Permissions

- Folders: usually `755`
- Files: usually `644`
- Confirm `assets/uploads/media/` is writable by PHP

## 6. Run First-Time Setup

- Visit `https://a1technovation.com/admin/setup.php`
- Create the single admin account
- Confirm setup completes without database errors

## 7. Verify Admin Access

- Visit `https://a1technovation.com/admin/login.php`
- Log in with the admin account
- Confirm the dashboard opens
- Confirm Blogs, Pages, Templates, Settings, and Media pages load

## 8. Verify Public Blog

- Visit `https://a1technovation.com/blog`
- Create and publish a test post from the CMS
- Open the published post on `https://a1technovation.com/blog/post-slug`
- Confirm featured image, excerpt, and content render correctly

## 9. Verify Media Uploads

- Upload a test image in the Media library
- Copy the generated media URL
- Confirm the file opens publicly
- Confirm the image can be used in a blog post

## 10. Verify Dynamic Pages

- Create a test service page from `admin/pages.php`
- Add a few widgets like Hero, Text, FAQ, and CTA
- Publish the page
- Confirm it opens on `https://a1technovation.com/services/page-slug`
- If you create a standard page, confirm it opens on `https://a1technovation.com/page/page-slug`
- Confirm navigation updates when `Show in nav` is enabled for service pages

## 11. Verify Global Settings and Templates

- Update site settings in `admin/settings.php`
- Confirm footer/contact values update on dynamic CMS pages
- Save one reusable section in `admin/templates.php`
- Confirm it can be inserted into the page builder

## 12. Final Go-Live Check

- Delete any test draft or placeholder content
- Keep one real admin account only
- Recheck live URLs:
- `https://a1technovation.com/admin/login.php`
- `https://a1technovation.com/blog`
- `https://a1technovation.com/`

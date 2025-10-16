# استخدم صورة رسمية لـ PHP مع Apache
FROM php:8.2-apache

# نسخ كل ملفات المشروع داخل مجلد الويب
COPY . /var/www/html/

# فتح المنفذ 80
EXPOSE 80

# تشغيل Apache
CMD ["apache2-foreground"]

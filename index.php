<?php
/**
 * Homepage - Qharleh Productions Africa
 * Photography & Videography Portfolio Website
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$db_active = false;
$categories = [];
$images = [];

$contact_success = null;
$contact_error = '';

// Handle Contact Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    if (!$conn) {
        $contact_success = false;
        $contact_error = "Our database is currently offline. Please contact us directly at info@qharlehproductions.com.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $contact_success = false;
            $contact_error = "Please fill in all fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $contact_success = false;
            $contact_error = "Please enter a valid email address.";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO messages (name, email, subject, message) VALUES (:name, :email, :subject, :message)");
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':subject' => $subject,
                    ':message' => $message
                ]);
                $contact_success = true;
            } catch (PDOException $e) {
                $contact_success = false;
                $contact_error = "Database save failed: " . $e->getMessage();
            }
        }
    }
}

if ($conn) {
    try {
        // Fetch categories
        $cat_query = $conn->query("SELECT * FROM categories ORDER BY name ASC");
        if ($cat_query) {
            $categories = $cat_query->fetchAll();
            $db_active = true;
        }

        // Fetch images with category slug
        $img_query = $conn->query("
            SELECT i.*, c.slug as category_slug, c.name as category_name 
            FROM images i 
            JOIN categories c ON i.category_id = c.id 
            ORDER BY i.created_at DESC
        ");
        if ($img_query) {
            $images = $img_query->fetchAll();
        }
    } catch (PDOException $e) {
        // Fallback to static if query fails
        $db_active = false;
    }
}

// Fallback static data if database is not active yet (helps show the website out of the box)
if (!$db_active) {
    $categories = [
        ['id' => 1, 'name' => 'Street Photography', 'slug' => 'street-photography'],
        ['id' => 2, 'name' => 'Studio Portraits', 'slug' => 'studio-portraits'],
        ['id' => 3, 'name' => 'Cinematic Video', 'slug' => 'cinematic-video']
    ];

    $images = [
        [
            'id' => 1,
            'category_slug' => 'street-photography',
            'category_name' => 'Street Photography',
            'image_path' => 'assets/images/street_photography.png',
            'title' => 'Haile Selassie Street Hustle',
            'description' => 'Daily street photography shot capturing the vibrant atmosphere opposite Easy Coach.'
        ],
        [
            'id' => 2,
            'category_slug' => 'studio-portraits',
            'category_name' => 'Studio Portraits',
            'image_path' => 'assets/images/portrait_photography.png',
            'title' => 'Golden Mood Portrait',
            'description' => 'High-end studio portrait showcasing professional off-camera flash lighting and styling.'
        ],
        [
            'id' => 3,
            'category_slug' => 'cinematic-video',
            'category_name' => 'Cinematic Video',
            'image_path' => 'assets/images/videography_action.png',
            'title' => 'Behind the Lens in Nairobi',
            'description' => 'Documentary cinematography session capturing local stories on a handheld cinema rig.'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title>Qharleh Productions Africa | Professional Photography & Videography Nairobi</title>
    <meta name="description" content="Qharleh Productions Africa - Professional photography and videography services in Nairobi. Specializing in street photography at Haile Selassie Avenue (opposite Easy Coach), events, and portraits.">
    <meta name="keywords" content="photography nairobi, videography kenya, street photography, haile selassie avenue, easy coach photography, portrait photographer, wedding videography nairobi">
    
    <!-- Google Fonts: Outfit (Headings) & Inter (Body) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome Icons CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Premium Stylesheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- DB Warning Banner for Administrators -->
    <?php if (!$db_active): ?>
        <div class="bg-warning text-dark text-center py-2 px-3 fw-bold" style="position: sticky; top: 0; z-index: 1100; font-size: 0.85rem;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            Developer Notice: Database is not connected. Showing demo showcase. 
            <a href="setup.php" class="text-decoration-underline text-dark ms-2">Run Web Setup Installer <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    <?php endif; ?>

    <!-- Booking submission notice -->
    <?php if ($contact_success === true): ?>
        <div class="bg-success text-white text-center py-3 px-3 fw-semibold" style="position: sticky; top: 0; z-index: 1100; font-size: 0.9rem; border-bottom: 2px solid rgba(255,255,255,0.2);">
            <i class="fa-solid fa-circle-check me-2"></i>
            Thank you for contacting Qharleh Productions Africa! Your booking/inquiry has been received.
        </div>
    <?php elseif ($contact_success === false): ?>
        <div class="bg-danger text-white text-center py-3 px-3 fw-semibold" style="position: sticky; top: 0; z-index: 1100; font-size: 0.9rem; border-bottom: 2px solid rgba(255,255,255,0.2);">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            Error sending booking request: <?php echo htmlspecialchars($contact_error); ?>
        </div>
    <?php endif; ?>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top" id="mainNavbar">
        <div class="container">
            <a class="navbar-brand" href="#home">
                Qharleh<span>Productions</span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#portfolio">Portfolio</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                        <li class="nav-item ms-lg-3">
                            <a class="btn btn-outline-warning btn-sm px-3 py-2 text-uppercase fw-bold" href="admin.php" style="font-size: 0.75rem; letter-spacing: 1px;">
                                <i class="fa-solid fa-gauge me-1"></i>Dashboard
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-3">
                            <a class="btn btn-outline-light btn-sm px-3 py-2 text-uppercase fw-bold" href="login.php" style="font-size: 0.75rem; letter-spacing: 1px; border-color: rgba(255,255,255,0.2);">
                                <i class="fa-solid fa-lock me-1"></i>Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header id="home" class="hero-section" style="background-image: url('assets/images/hero_background.png');">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div class="hero-logo-sub">Qharleh Productions Africa</div>
            <h1 class="hero-title">We <span>Capture Moments</span>,<br>To Tell Your Story</h1>
            <p class="hero-motto">"<?php echo htmlspecialchars(SITE_MOTTO); ?>"</p>
            <a href="#portfolio" class="btn hero-btn">Explore Portfolio</a>
        </div>
    </header>

    <!-- About Section (Mission, Vision, and Motto) -->
    <section id="about" class="py-5" style="background-color: rgba(255, 255, 255, 0.01);">
        <div class="container py-5">
            <div class="section-header">
                <span class="section-tag">Who We Are</span>
                <h2 class="section-title">Our Vision & Mission</h2>
            </div>
            
            <div class="row g-4 justify-content-center">
                <!-- Mission Card -->
                <div class="col-md-5">
                    <div class="glass-card">
                        <div class="card-icon-wrapper">
                            <i class="fa-solid fa-eye-low-vision"></i>
                        </div>
                        <h3>Our Mission</h3>
                        <p class="text-muted mt-3 mb-0" style="line-height: 1.8;">
                            To tell compelling African stories and preserve timeless memories through the lens of creative precision, utilizing state-of-the-art photography and cinematic videography to elevate local perspectives onto the global stage.
                        </p>
                    </div>
                </div>
                
                <!-- Vision Card -->
                <div class="col-md-5">
                    <div class="glass-card">
                        <div class="card-icon-wrapper">
                            <i class="fa-solid fa-compass"></i>
                        </div>
                        <h3>Our Vision</h3>
                        <p class="text-muted mt-3 mb-0" style="line-height: 1.8;">
                            To be Africa's premier storytelling hub through state-of-the-art visuals, setting benchmarks in authenticity, technical execution, and artistic innovation that inspire generations of visual storytellers.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Motto Callout Banner -->
            <div class="row mt-5 justify-content-center">
                <div class="col-md-10">
                    <div class="text-center p-5 rounded-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #121216 0%, #1c1c24 100%); border: 1px solid var(--border-gold);">
                        <div class="position-absolute" style="top: -20px; right: -20px; font-size: 8rem; color: rgba(197,168,128,0.03); transform: rotate(15deg);"><i class="fa-solid fa-quote-right"></i></div>
                        <h4 class="text-uppercase text-muted small fw-bold tracking-widest mb-3" style="letter-spacing: 3px;">Our Philosophy</h4>
                        <blockquote class="blockquote mb-0">
                            <p class="display-6 font-italic text-light font-serif mb-4" style="font-family: 'Outfit', sans-serif; font-style: italic; font-weight: 300;">
                                "Capture moment, tell a story"
                            </p>
                            <footer class="blockquote-footer text-warning fw-semibold mt-2" style="font-family: 'Outfit', sans-serif;">Qharleh Productions Africa</footer>
                        </blockquote>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-5" style="background-color: var(--bg-dark);">
        <div class="container py-5">
            <div class="section-header">
                <span class="section-tag">What We Offer</span>
                <h2 class="section-title">Our Services</h2>
            </div>
            
            <div class="row g-4">
                <!-- Street Photography (Special Highlighted Request) -->
                <div class="col-lg-4 col-md-6">
                    <div class="service-card border border-warning">
                        <div class="service-img-wrapper">
                            <span class="service-badge"><i class="fa-solid fa-star me-1"></i> Featured Daily</span>
                            <img src="assets/images/street_photography.png" alt="Street Photography Nairobi" class="service-img">
                        </div>
                        <div class="service-info d-flex flex-column justify-content-between h-100">
                            <div>
                                <h3 class="service-title">Street Photography</h3>
                                <p class="service-text">
                                    Candid portraits, urban cityscapes, and visual documentation of vibrant Nairobi street life. We capture the pulse of the city as it beats.
                                </p>
                            </div>
                            <div class="service-highlight text-warning">
                                <i class="fa-solid fa-location-dot me-2"></i>
                                <strong>Every Day:</strong> Haile Selassie Avenue, opposite Easy Coach, Nairobi
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Event Photography -->
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-img-wrapper">
                            <span class="service-badge" style="background-color: var(--text-light); color: #000;">Corporate / Private</span>
                            <img src="assets/images/portrait_photography.png" alt="Event Photography and Portraiture" class="service-img">
                        </div>
                        <div class="service-info">
                            <h3 class="service-title">Studio Portraits & Events</h3>
                            <p class="service-text">
                                Professional studio portrait sessions with controlled, high-fashion lighting and comprehensive coverage of weddings, private ceremonies, and corporate galas.
                            </p>
                            <div class="service-highlight">
                                <i class="fa-solid fa-camera me-2"></i> Custom outdoor sessions & premium studio lighting
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cinematic Video -->
                <div class="col-lg-4 col-md-6">
                    <div class="service-card">
                        <div class="service-img-wrapper">
                            <span class="service-badge" style="background-color: var(--text-light); color: #000;">Cinematic</span>
                            <img src="assets/images/videography_action.png" alt="Videography Services" class="service-img">
                        </div>
                        <div class="service-info">
                            <h3 class="service-title">Cinematic Video Production</h3>
                            <p class="service-text">
                                Full-scale videography, directing, and editing for documentaries, commercials, music videos, and storytelling reels. Captured on state-of-the-art setups.
                            </p>
                            <div class="service-highlight">
                                <i class="fa-solid fa-video me-2"></i> 4K UHD capture, drone footage, and color grading
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Portfolio Gallery Section -->
    <section id="portfolio" class="py-5" style="background-color: rgba(255, 255, 255, 0.01);">
        <div class="container py-5">
            <div class="section-header">
                <span class="section-tag">Showcase</span>
                <h2 class="section-title">Selected Projects</h2>
            </div>

            <!-- Categories Filter Buttons -->
            <div class="portfolio-filters">
                <button class="btn filter-btn active" data-filter="all">All Projects</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="btn filter-btn" data-filter="<?php echo htmlspecialchars($cat['slug']); ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Gallery Grid -->
            <div class="row g-4 gallery-grid">
                <?php if (empty($images)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="fa-regular fa-images text-muted fa-3x mb-3"></i>
                        <p class="text-muted">No portfolio items uploaded yet. Log in to the administrator portal to upload files!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($images as $img): ?>
                        <div class="col-lg-4 col-md-6 gallery-card-col" data-category="<?php echo htmlspecialchars($img['category_slug']); ?>">
                            <div class="gallery-item" onclick="openLightbox('<?php echo htmlspecialchars($img['image_path']); ?>', '<?php echo htmlspecialchars($img['title']); ?>', '<?php echo htmlspecialchars($img['description']); ?>', '<?php echo htmlspecialchars($img['category_name']); ?>')">
                                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="<?php echo htmlspecialchars($img['title']); ?>" loading="lazy">
                                <div class="gallery-overlay">
                                    <span class="gallery-category"><?php echo htmlspecialchars($img['category_name']); ?></span>
                                    <h4 class="gallery-title"><?php echo htmlspecialchars($img['title']); ?></h4>
                                    <p class="gallery-desc"><?php echo htmlspecialchars($img['description']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Lightbox Modal -->
    <div id="galleryLightbox" class="lightbox-modal">
        <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        <img id="lightboxImg" class="lightbox-content" src="" alt="Zoomed view">
        <div class="lightbox-caption">
            <span id="lightboxCat" class="text-uppercase tracking-wider small fw-semibold text-warning" style="letter-spacing: 2px;">Category</span>
            <h4 id="lightboxTitle" class="mt-1">Image Title</h4>
            <p id="lightboxDesc" class="text-muted small">Image Description</p>
        </div>
    </div>

    <!-- Contact Section -->
    <section id="contact" class="py-5" style="background-color: var(--bg-dark);">
        <div class="container py-5">
            <div class="section-header">
                <span class="section-tag">Let's Work Together</span>
                <h2 class="section-title">Book a Session</h2>
            </div>
            
            <div class="row g-5">
                <!-- Info Column -->
                <div class="col-lg-5">
                    <div class="contact-info-block d-flex flex-column justify-content-between">
                        <div>
                            <h3 class="mb-4">Get In Touch</h3>
                            <p class="text-muted" style="line-height: 1.8;">
                                Have an upcoming wedding, corporate event, or documentary project? Or do you want to book a creative street photography session at our daily location? Leave us a message.
                            </p>
                            
                            <div class="mt-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="contact-icon"><i class="fa-solid fa-map-location-dot"></i></div>
                                    <div>
                                        <p class="text-muted mb-0 small">Daily Base Location</p>
                                        <p class="mb-0 fw-semibold">Haile Selassie Avenue, Opposite Easy Coach, Nairobi</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center mb-3">
                                    <div class="contact-icon"><i class="fa-solid fa-envelope"></i></div>
                                    <div>
                                        <p class="text-muted mb-0 small">Email Address</p>
                                        <p class="mb-0 fw-semibold">info@qharlehproductions.com</p>
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center">
                                    <div class="contact-icon"><i class="fa-solid fa-phone"></i></div>
                                    <div>
                                        <p class="text-muted mb-0 small">Phone / WhatsApp</p>
                                        <p class="mb-0 fw-semibold">+254 700 000 000</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning border-0 bg-dark-subtle mt-4 mb-0">
                            <i class="fa-solid fa-street-view me-2 text-warning"></i>
                            <strong>Street Photography:</strong> Look out for our team daily on Haile Selassie Avenue wearing branded Qharleh Productions straps.
                        </div>
                    </div>
                </div>
                
                <!-- Form Column -->
                <div class="col-lg-7">
                    <form method="POST" action="#contact" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Your Name</label>
                            <input type="text" name="name" class="form-control contact-form-control" required placeholder="John Doe" value="<?php echo isset($_POST['name']) && $contact_success === false ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Email Address</label>
                            <input type="email" name="email" class="form-control contact-form-control" required placeholder="john@example.com" value="<?php echo isset($_POST['email']) && $contact_success === false ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Subject / Service Type</label>
                            <select name="subject" class="form-select contact-form-control">
                                <option value="Street Photography (Haile Selassie Ave)">Street Photography (Haile Selassie Ave)</option>
                                <option value="Event Photography & Videography">Event Photography & Videography</option>
                                <option value="Studio / Outdoors Portrait Session">Studio / Outdoors Portrait Session</option>
                                <option value="Commercial Video Production">Commercial Video Production</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Your Message</label>
                            <textarea name="message" class="form-control contact-form-control" rows="5" required placeholder="Detail your project, dates, or booking inquiries..."><?php echo isset($_POST['message']) && $contact_success === false ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="submit_contact" class="btn btn-contact w-100">Send Booking Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-custom">
        <div class="container text-center">
            <h2 class="footer-logo">Qharleh<span>Productions</span></h2>
            <p class="footer-motto">"Capture moment, tell a story"</p>
            
            <!-- Social Media Logos -->
            <div class="social-icons justify-content-center">
                <a href="https://instagram.com" class="social-icon-btn" target="_blank" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                <a href="https://facebook.com" class="social-icon-btn" target="_blank" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="https://youtube.com" class="social-icon-btn" target="_blank" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                <a href="https://tiktok.com" class="social-icon-btn" target="_blank" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
                <a href="https://twitter.com" class="social-icon-btn" target="_blank" aria-label="Twitter"><i class="fa-brands fa-x-twitter"></i></a>
            </div>

            <!-- Footer Links -->
            <div class="d-flex justify-content-center">
                <ul class="footer-nav">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#portfolio">Portfolio</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div>
                    &copy; 2026 Qharleh Productions Africa. All rights reserved.
                </div>
                <div>
                    Designed by Antigravity | 
                    <a href="login.php"><i class="fa-solid fa-lock ms-1 me-1"></i> Admin Portal</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Vanilla Javascript Logic -->
    <script>
        // Navbar Scroll Styling
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('mainNavbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Portfolio filtering logic
        const filterButtons = document.querySelectorAll('.filter-btn');
        const galleryItems = document.querySelectorAll('.gallery-card-col');

        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from other buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                const filterValue = button.getAttribute('data-filter');

                galleryItems.forEach(item => {
                    if (filterValue === 'all' || item.getAttribute('data-category') === filterValue) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });

        // Lightbox modal operations
        const lightbox = document.getElementById('galleryLightbox');
        const lightboxImg = document.getElementById('lightboxImg');
        const lightboxCat = document.getElementById('lightboxCat');
        const lightboxTitle = document.getElementById('lightboxTitle');
        const lightboxDesc = document.getElementById('lightboxDesc');

        function openLightbox(path, title, desc, cat) {
            lightboxImg.src = path;
            lightboxCat.textContent = cat;
            lightboxTitle.textContent = title;
            lightboxDesc.textContent = desc;
            lightbox.classList.add('show');
            document.body.style.overflow = 'hidden'; // Stop scrolling behind modal
        }

        function closeLightbox() {
            lightbox.classList.remove('show');
            document.body.style.overflow = 'auto'; // Enable scrolling
        }

        // Close lightbox on click outside image
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });

        // Close lightbox on escape key press
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && lightbox.classList.contains('show')) {
                closeLightbox();
            }
        });
    </script>
</body>
</html>

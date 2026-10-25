import math
from PIL import Image, ImageDraw, ImageFont

W, H = 1672, 941
img = Image.new('RGB', (W, H), '#ffffff')
draw = ImageDraw.Draw(img)

# 1. Background radial gradients and subtle tech ambiance
glow = Image.new('RGBA', (W, H), (255, 255, 255, 0))
gd = ImageDraw.Draw(glow)
for r in range(500, 0, -15):
    alpha = int(35 * (1 - r / 500))
    gd.ellipse([1250 - r, 420 - r, 1250 + r, 420 + r], fill=(219, 234, 254, alpha))
for r in range(350, 0, -15):
    alpha = int(20 * (1 - r / 350))
    gd.ellipse([300 - r, 750 - r, 300 + r, 750 + r], fill=(238, 242, 255, alpha))
img.paste(glow, (0, 0), glow)

# Subtle dot grid pattern on sides
for x in range(40, 180, 24):
    for y in range(40, 900, 24):
        draw.ellipse([x, y, x + 2, y + 2], fill='#e2e8f0')
for x in range(1500, 1640, 24):
    for y in range(40, 900, 24):
        draw.ellipse([x, y, x + 2, y + 2], fill='#e2e8f0')

# Fonts
font_title = ImageFont.truetype('C:/Windows/Fonts/segoeuib.ttf', 72)
font_sub = ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 24)
font_icon_lbl = ImageFont.truetype('C:/Windows/Fonts/segoeuib.ttf', 16)
font_tagline = ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 16)
font_card_head = ImageFont.truetype('C:/Windows/Fonts/segoeuib.ttf', 20)
font_card_sub = ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 14)
font_badge = ImageFont.truetype('C:/Windows/Fonts/segoeuib.ttf', 16)

# Left Column Typography
# Title
draw.text((100, 160), 'SEO Services:', font=font_title, fill='#0f172a')
draw.text((100, 245), 'Search Visibility &', font=font_title, fill='#2563eb')
draw.text((100, 330), 'Qualified Leads', font=font_title, fill='#1d4ed8')

# Accent line
draw.rounded_rectangle([102, 435, 182, 440], radius=3, fill='#2563eb')

# Subtitle
draw.text((100, 465), 'Connect technical SEO, useful content, and qualified inquiries.', font=font_sub, fill='#475569')
draw.text((100, 505), 'Defined scope, clear governance, and measurable search outcomes.', font=font_sub, fill='#64748b')

# 4 Circular Icon Pills
icons_data = [
    ('Technical SEO', 'gear'),
    ('Useful Content', 'content'),
    ('Search Console', 'search'),
    ('Qualified Leads', 'target')
]

pill_x = [150, 305, 460, 615]
pill_y = 660
r_circle = 38

for i, (label, itype) in enumerate(icons_data):
    cx = pill_x[i]
    cy = pill_y
    # Circle with soft outline
    draw.ellipse([cx - r_circle - 1, cy - r_circle - 1, cx + r_circle + 1, cy + r_circle + 1], fill='#eff6ff', outline='#bfdbfe', width=2)
    
    # Draw simple clean vector icon inside
    if itype == 'gear':
        # Technical SEO gear / server
        draw.rounded_rectangle([cx - 14, cy - 12, cx + 14, cy + 12], radius=4, outline='#2563eb', width=2)
        draw.line([cx - 8, cy - 4, cx + 8, cy - 4], fill='#2563eb', width=2)
        draw.line([cx - 8, cy + 4, cx + 8, cy + 4], fill='#2563eb', width=2)
        draw.ellipse([cx + 5, cy - 5, cx + 9, cy - 1], fill='#10b981')
    elif itype == 'content':
        # File / document with text lines
        draw.rounded_rectangle([cx - 12, cy - 15, cx + 12, cy + 15], radius=3, outline='#2563eb', width=2)
        draw.line([cx - 7, cy - 7, cx + 7, cy - 7], fill='#2563eb', width=2)
        draw.line([cx - 7, cy, cx + 7, cy], fill='#2563eb', width=2)
        draw.line([cx - 7, cy + 7, cx + 3, cy + 7], fill='#2563eb', width=2)
    elif itype == 'search':
        # Magnifying glass with upward chart
        draw.ellipse([cx - 14, cy - 14, cx + 4, cy + 4], outline='#2563eb', width=2)
        draw.line([cx + 1, cy + 1, cx + 12, cy + 12], fill='#2563eb', width=3)
        draw.line([cx - 10, cy - 2, cx - 6, cy - 8, cx - 1, cy - 4], fill='#2563eb', width=2)
    elif itype == 'target':
        # Target bullseye
        draw.ellipse([cx - 14, cy - 14, cx + 14, cy + 14], outline='#2563eb', width=2)
        draw.ellipse([cx - 7, cy - 7, cx + 7, cy + 7], fill='#2563eb')
        draw.ellipse([cx - 3, cy - 3, cx + 3, cy + 3], fill='#ffffff')

    # Label below
    bbox = font_icon_lbl.getbbox(label)
    tw = bbox[2] - bbox[0]
    draw.text((cx - tw // 2, cy + r_circle + 14), label, font=font_icon_lbl, fill='#0f172a')

# Tagline below
tagline_y = 810
draw.line([100, tagline_y + 10, 240, tagline_y + 10], fill='#cbd5e1', width=1)
draw.text((255, tagline_y), 'Define Scope.  •  Measure Outcomes.  •  Attract Suitable Buyers.', font=font_tagline, fill='#64748b')
draw.line([710, tagline_y + 10, 800, tagline_y + 10], fill='#cbd5e1', width=1)

# Right Side - Modern 3D Floating Search & Lead Showcase Card
card_x1, card_y1, card_x2, card_y2 = 870, 160, 1580, 780

# Card Shadow
shadow = Image.new('RGBA', (W, H), (0, 0, 0, 0))
sd = ImageDraw.Draw(shadow)
for o in range(16, 0, -2):
    sd.rounded_rectangle([card_x1 - o, card_y1 - o + 6, card_x2 + o, card_y2 + o + 10], radius=28 + o, fill=(15, 23, 42, int(18 * (1 - o / 16))))
img.paste(shadow, (0, 0), shadow)

# Main Card Body
draw.rounded_rectangle([card_x1, card_y1, card_x2, card_y2], radius=28, fill='#ffffff', outline='#e2e8f0', width=2)

# Card Header Bar (Browser-like mock)
draw.rounded_rectangle([card_x1 + 24, card_y1 + 24, card_x2 - 24, card_y1 + 76], radius=14, fill='#f8fafc', outline='#e2e8f0', width=1)
# Search dots
draw.ellipse([card_x1 + 44, card_y1 + 46, card_x1 + 54, card_y1 + 56], fill='#ef4444')
draw.ellipse([card_x1 + 60, card_y1 + 46, card_x1 + 70, card_y1 + 56], fill='#f59e0b')
draw.ellipse([card_x1 + 76, card_y1 + 46, card_x1 + 86, card_y1 + 56], fill='#10b981')
# Search input
draw.rounded_rectangle([card_x1 + 106, card_y1 + 36, card_x2 - 40, card_y1 + 64], radius=8, fill='#ffffff', outline='#cbd5e1', width=1)
draw.text((card_x1 + 120, card_y1 + 41), '🔍  https://google.com/search?q=best+seo+services+qualified+leads', font=font_card_sub, fill='#64748b')

# Organic Search Result #1 Mockup inside the card
draw.rounded_rectangle([card_x1 + 24, card_y1 + 96, card_x2 - 24, card_y1 + 250], radius=18, fill='#f0f7ff', outline='#bfdbfe', width=2)
draw.text((card_x1 + 46, card_y1 + 112), 'https://a1technovation.com/pages/services-seo', font=ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 13), fill='#059669')
draw.text((card_x1 + 46, card_y1 + 134), 'SEO Services That Deliver Search Visibility & Qualified Leads', font=ImageFont.truetype('C:/Windows/Fonts/segoeuib.ttf', 20), fill='#1d4ed8')
draw.text((card_x1 + 46, card_y1 + 168), 'A1 Technovation connects technical SEO, useful content, and page improvements around', font=ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 14), fill='#475569')
draw.text((card_x1 + 46, card_y1 + 190), 'your goals. Helping suitable buyers find your offer and take the next step.', font=ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 14), fill='#475569')
# Rating stars
draw.text((card_x1 + 46, card_y1 + 218), '★★★★★ 4.9 (150+ Reviews)  •  Rank #1 Top Unpaid Result  •  White-Hat', font=ImageFont.truetype('C:/Windows/Fonts/segoeuib.ttf', 12), fill='#d97706')

# Analytics Metrics & Trajectory Graph
draw.text((card_x1 + 36, card_y1 + 276), 'Organic Visibility & Lead Conversion Growth', font=font_card_head, fill='#0f172a')
draw.text((card_x1 + 36, card_y1 + 304), 'Search Console Performance Trend (Q1 - Q4)', font=font_card_sub, fill='#64748b')

# Metric Pills inside card
draw.rounded_rectangle([card_x1 + 36, card_y1 + 340, card_x1 + 236, card_y1 + 415], radius=14, fill='#f8fafc', outline='#e2e8f0', width=1)
draw.text((card_x1 + 48, card_y1 + 350), 'Total Impressions', font=font_card_sub, fill='#64748b')
draw.text((card_x1 + 48, card_y1 + 372), '3.1M  ↗ +38%', font=font_card_head, fill='#2563eb')

draw.rounded_rectangle([card_x1 + 252, card_y1 + 340, card_x1 + 452, card_y1 + 415], radius=14, fill='#f8fafc', outline='#e2e8f0', width=1)
draw.text((card_x1 + 264, card_y1 + 350), 'Avg. CTR', font=font_card_sub, fill='#64748b')
draw.text((card_x1 + 264, card_y1 + 372), '4.8%  ↗ +1.4%', font=font_card_head, fill='#0284c7')

draw.rounded_rectangle([card_x1 + 468, card_y1 + 340, card_x2 - 36, card_y1 + 415], radius=14, fill='#f8fafc', outline='#e2e8f0', width=1)
draw.text((card_x1 + 480, card_y1 + 350), 'Qualified Leads', font=font_card_sub, fill='#64748b')
draw.text((card_x1 + 480, card_y1 + 372), '482  ↗ +52%', font=font_card_head, fill='#10b981')

# Smooth Graph Curve
gx_start, gy_bottom = card_x1 + 46, card_y2 - 50
gx_end, gy_top = card_x2 - 46, card_y1 + 450
points = [
    (gx_start, gy_bottom - 20),
    (gx_start + 100, gy_bottom - 45),
    (gx_start + 220, gy_bottom - 60),
    (gx_start + 340, gy_bottom - 95),
    (gx_start + 460, gy_bottom - 110),
    (gx_start + 560, gy_bottom - 145),
    (gx_end, gy_bottom - 180)
]
# Draw subtle grid lines
for y_line in range(gy_bottom, gy_top, -35):
    draw.line([gx_start, y_line, gx_end, y_line], fill='#f1f5f9', width=1)

# Draw graph fill
poly = [(gx_start, gy_bottom)] + points + [(gx_end, gy_bottom)]
graph_overlay = Image.new('RGBA', (W, H), (0, 0, 0, 0))
god = ImageDraw.Draw(graph_overlay)
god.polygon(poly, fill=(37, 99, 235, 30))
img.paste(graph_overlay, (0, 0), graph_overlay)

# Graph line
for k in range(len(points) - 1):
    draw.line([points[k], points[k+1]], fill='#2563eb', width=4)
for pt in points:
    draw.ellipse([pt[0] - 5, pt[1] - 5, pt[0] + 5, pt[1] + 5], fill='#ffffff', outline='#2563eb', width=3)

# Floating Badge 1 (Top Right Overlay)
fb1_x, fb1_y = card_x2 - 180, card_y1 - 25
draw.rounded_rectangle([fb1_x, fb1_y, fb1_x + 195, fb1_y + 50], radius=25, fill='#ffffff', outline='#bfdbfe', width=2)
draw.text((fb1_x + 16, fb1_y + 14), '🏆  Top #1 Ranking', font=font_badge, fill='#1e293b')

# Floating Badge 2 (Bottom Left Overlay)
fb2_x, fb2_y = card_x1 - 35, card_y2 - 60
draw.rounded_rectangle([fb2_x, fb2_y, fb2_x + 220, fb2_y + 52], radius=26, fill='#ffffff', outline='#86efac', width=2)
draw.text((fb2_x + 18, fb2_y + 15), '🎯  +52% Qualified Leads', font=font_badge, fill='#065f46')

# Save high quality WEBP and JPG
img.save('assets/images/seo-search-visibility-qualified-leads.webp', 'WEBP', quality=95)
img.save('assets/images/seo-search-visibility-qualified-leads.jpg', 'JPEG', quality=95)
print('Banner created successfully!')

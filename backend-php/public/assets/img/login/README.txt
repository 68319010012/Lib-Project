Login background photos
=======================

Put your premium library photos here to make the login page slideshow use them:

  bg-1.jpg
  bg-2.jpg
  bg-3.jpg
  bg-4.jpg
  bg-5.jpg

Notes
-----
- 1 to 5 files. Any that are missing are simply skipped.
- Landscape orientation, ~1920x1080 (or larger) looks best.
- .jpg with these exact names. To change the list/names, edit
  assets/js/login-bg.js (the SOURCES array).
- If NONE of these bg-*.jpg files exist, the page uses the shipped premium
  library scenes (scene-1.svg … scene-3.svg) so the slideshow still runs.
  Adding even one bg-*.jpg switches the slideshow to your photos automatically.
- The slideshow cross-fades every 6.5s with a slow Ken Burns zoom; a dark
  vignette overlay keeps the login card readable over any photo.

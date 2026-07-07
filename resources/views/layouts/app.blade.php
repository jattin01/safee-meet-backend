<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'SafeeMeet Admin')</title>

  {{-- CSS --}}
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <!-- Quill CSS -->
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
  <!-- Quill JS -->
  <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>


<style>
   .ql-toolbar.ql-snow {
        background-color: #1a1a1a;
        border-color: #000 !important;
    }
    .ql-container.ql-snow {
        background-color: #1a1a1a;
        border-color: #000 !important;
        color: #cdd9f0;
        min-height: 200px;
    }
    .ql-snow .ql-stroke {
        stroke: #8899aa !important;
    }
    .ql-snow .ql-fill {
        fill: #8899aa !important;
    }
    .ql-editor.ql-blank::before {
    color: #fff;
}
    .ql-snow .ql-picker {
        color: #8899aa !important;
    }
    </style>

</head>
<body class="bg-black text-white antialiased" style="font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">

  <div class="flex">

    {{-- SIDEBAR --}}
    @include('partials.sidebar')

    {{-- Backdrop for small screens --}}
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black/50 z-20 hidden md:hidden"></div>

    {{-- RIGHT SIDE --}}
    <div class="flex-1 min-h-screen flex flex-col md:ml-[250px] w-full">

      {{-- HEADER --}}
      @include('partials.header')

      {{-- PAGE CONTENT --}}
      <main class="flex-1 bg-[#1a1a1a] p-6">
        @yield('content')
      </main>

      {{-- FOOTER --}}
      @include('partials.footer')

    </div>
  </div>

  {{-- Extra scripts per page --}}
  @yield('scripts')

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const toggle = document.getElementById('navToggle');
      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');

      if (!toggle || !sidebar) return;

      function openSidebar() {
        sidebar.classList.add('translate-x-0');
        sidebar.classList.remove('-translate-x-full');
        if (backdrop) { backdrop.classList.remove('hidden'); backdrop.classList.add('block'); }
      }

      function closeSidebar() {
        sidebar.classList.remove('translate-x-0');
        sidebar.classList.add('-translate-x-full');
        if (backdrop) { backdrop.classList.add('hidden'); backdrop.classList.remove('block'); }
      }

      toggle.addEventListener('click', function () {
        if (sidebar.classList.contains('translate-x-0')) closeSidebar(); else openSidebar();
      });

      if (backdrop) backdrop.addEventListener('click', closeSidebar);

      // Keep behavior consistent on resize
      const mq = window.matchMedia('(min-width: 1024px)');
      function handleMq(e) {
        if (e.matches) {
          sidebar.classList.remove('-translate-x-full');
          sidebar.classList.remove('translate-x-0');
          if (backdrop) { backdrop.classList.add('hidden'); backdrop.classList.remove('block'); }
        } else {
          sidebar.classList.add('-translate-x-full');
        }
      }
      mq.addListener(handleMq);
      handleMq(mq);
    });
  </script>

</body>
</html>

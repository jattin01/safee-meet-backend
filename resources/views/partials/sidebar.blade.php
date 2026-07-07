@php
  $currentAdmin = auth('admin')->user();
  $dashboardRoute = $currentAdmin?->role?->slug === 'super_admin'
      ? 'super-admin.dashboard'
      : 'admin.dashboard';
@endphp

<aside id="sidebar" class="z-30 flex flex-col border-r border-[#1a1a1a] bg-[#000] w-[250px] min-h-screen fixed left-0 top-0 transform transition-transform duration-300 md:translate-x-0 -translate-x-full">
  <div class="flex  items-center gap-3  border-[#252b3b] px-5">
    
    <span class="text-[15px] font-bold tracking-wide text-white  py-2">
       <img src="{{ asset('images/logo.png') }}" alt="Logo" class="bg-[#fff] w-[100px]" />
    </span>
  </div>

  <nav class="flex-1 overflow-y-auto px-3 py-5">
   

    <a href="{{ route($dashboardRoute) }}"
       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition
       {{ request()->is('dashboard') || request()->is('super-admin/dashboard') ? 'bg-[#DC131C] text-white' : 'text-[#8f98ad] hover:bg-[#1b2230] hover:text-white' }}">
      <i class="fa-solid fa-gauge-high w-4 text-center"></i>
      <span>Dashboard</span>
    </a>

    <a href="{{ url('/users') }}"
       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition
       {{ request()->is('users*') ? 'bg-[#DC131C] text-white' : 'text-[#8f98ad] hover:bg-[#1b2230] hover:text-white' }}">
      <i class="fa-solid fa-users w-4 text-center"></i>
      <span>Users</span>
    </a>

    <a href="{{ route('admins.index') }}"
       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition
       {{ request()->is('admins*') ? 'bg-[#DC131C] text-white' : 'text-[#8f98ad] hover:bg-[#1b2230] hover:text-white' }}">
      <i class="fa-solid fa-user-shield w-4 text-center"></i>
      <span>Admins</span>
    </a>

    <a href="{{ url('/verification') }}"
       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition
       {{ request()->is('verification*') ? 'bg-[#DC131C] text-white' : 'text-[#8f98ad] hover:bg-[#1b2230] hover:text-white' }}">
      <i class="fa-solid fa-user-check w-4 text-center"></i>
      <span>Verification</span>
      <span class="ml-auto rounded bg-[#0ab39c]/15 px-2 py-0.5 text-[11px] font-bold text-[#0ab39c]">234</span>
    </a>

    <a href="{{ url('/subscription') }}"
       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition
       {{ request()->is('subscription*') ? 'bg-[#DC131C] text-white' : 'text-[#8f98ad] hover:bg-[#1b2230] hover:text-white' }}">
      <i class="fa-solid fa-credit-card w-4 text-center"></i>
      <span>Subscriptions</span>
    </a>

    <a href="{{ url('/incidents') }}"
       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition
       {{ request()->is('incidents*') ? 'bg-[#DC131C] text-white' : 'text-[#8f98ad] hover:bg-[#1b2230] hover:text-white' }}">
      <i class="fa-solid fa-triangle-exclamation w-4 text-center"></i>
      <span>Incidents</span>
      <span class="ml-auto rounded bg-[#f06548]/15 px-2 py-0.5 text-[11px] font-bold text-[#f06548]">3</span>
    </a>

    <a href="{{ url('/revenue') }}"
       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition
       {{ request()->is('revenue*') ? 'bg-[#DC131C] text-white' : 'text-[#8f98ad] hover:bg-[#1b2230] hover:text-white' }}">
      <i class="fa-solid fa-chart-line w-4 text-center"></i>
      <span>Revenue</span>
    </a>

    <a href="{{ url('/terms') }}"
       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition
       {{ request()->is('terms*') ? 'bg-[#DC131C] text-white' : 'text-[#8f98ad] hover:bg-[#1b2230] hover:text-white' }}">
      <i class="fa-solid fa-file-contract w-4 text-center"></i>
      <span>Terms & Conditions</span>
    </a>

  
    

    
  </nav>

  <div class="border-t border-[#1a1a1a] p-4">
    <a href="#" class="flex items-center gap-3 rounded-md bg-[#1a1a1a] px-3 py-2.5 text-sm font-medium text-[#8f98ad] transition hover:text-white">
      <i class="fa-solid fa-arrow-left w-4 text-center"></i>
      <span>Back to App</span>
    </a>
  </div>
</aside>

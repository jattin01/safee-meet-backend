<header class="sticky top-0 z-20 border-b border-[#1a1a1a] bg-[#000] px-6 py-3 backdrop-blur">
  <div class="flex items-center gap-4">
    <button id="navToggle" class="grid h-9 w-9 place-items-center rounded-md border border-[#1a1a1a] bg-[#1a1a1a] text-[#8f98ad] transition hover:text-white" aria-label="Toggle navigation" aria-controls="sidebar">
      <i class="fa-solid fa-bars-staggered text-sm"></i>
    </button>

    <div class="hidden min-w-[260px] max-w-xl flex-1 items-center gap-3 rounded-md border border-[#1a1a1a] bg-[#1a1a1a] px-3 py-2.5 md:flex">
      <i class="fa-solid fa-magnifying-glass text-sm text-[#697386]"></i>
      <input
        type="text"
        placeholder="Search users, meetings, incidents..."
        class="w-full border-none bg-transparent text-sm text-white outline-none placeholder:text-[#697386]"
      >
    </div>

    <div class="ml-auto flex items-center gap-2">
      <button class="hidden h-9 items-center gap-2 rounded-md border border-[#1a1a1a] bg-[#1a1a1a] px-3 text-sm font-medium text-[#cbd2e1] transition hover:text-white lg:inline-flex">
        <i class="fa-regular fa-calendar text-[#8f98ad]"></i>
        {{ now()->format('M d') }}
      </button>

     

      <button class="relative grid h-9 w-9 place-items-center rounded-md border border-[#1a1a1a] bg-[#1a1a1a] text-[#8f98ad] transition hover:text-white" aria-label="Notifications">
        <i class="fa-regular fa-bell"></i>
        <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-[#DC131C]"></span>
      </button>

      <div class="ml-2 flex items-center gap-3 border-l border-[#252b3b] pl-4">
        <div class="grid h-9 w-9 place-items-center rounded-md bg-[#DC131C] text-sm font-bold text-white">H</div>
        <div class="hidden leading-tight sm:block">
          <p class="text-sm font-semibold text-white">Hari</p>
          <p class="text-xs text-[#8f98ad]">Admin</p>
        </div>
      </div>
    </div>
  </div>
</header>

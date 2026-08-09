@extends('layouts.legacy')

@section('content')
<section class="">
    <div class="content mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <!-- Judul -->
            <h2 class="fw-bold text-dark mb-0">Data Obat</h2>

            <!-- Bagian Tombol dan Pencarian -->
            <div class="d-flex align-items-between gap-3">
                <!-- Input Pencarian -->
                <form action="{{ route('admin.obat.index') }}" method="GET" class="input-group" style="max-width: 200px;">
                    <span class="input-group-text bg-primary text-white"><i class="bx bx-search"></i></span>
                    <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="Cari Obat...">
                </form>

                <!-- Tombol Tambah Obat -->
                <div>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#tambahObatModal">
                        <i class="bx bx-plus"></i> Tambah
                    </button>
                </div>
            </div>
        </div>

        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif
        
        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <div class="border bg-white border-secondary border-opacity-75 p-2 mb-2 rounded-3 overflow-hidden">
            <table class="table table-hover align-middle" id="obatTable">
                <thead class="bg-primary text-white">
                    <tr>
                        <th>Gambar</th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Stok</th>
                        <th>Jenis Obat</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($obats as $obat)
                        <tr>
                            <td>
                                <img src="{{ asset('Assets/Obat/' . $obat->gambar) }}" alt="gambar obat" style="width: 110px; height: 110px; object-fit: cover;">
                            </td>
                            <td>{{ $obat->kode }}</td>
                            <td>{{ $obat->nama }}</td>
                            <td>{{ $obat->stok }}</td>
                            <td>{{ $obat->jenis_obat }}</td>
                            <td>{{ $obat->kategori }}</td>
                            <td>Rp. {{ number_format($obat->harga, 2, ',', '.') }}</td>
                            <td class="text-center">
                                <button class="btn btn-warning btn-sm rounded-2" data-bs-toggle="modal" data-bs-target="#editObatModal{{ $obat->kode }}">
                                    <i class="bx bx-edit"></i> Edit
                                </button>
                                <form action="{{ route('admin.obat.destroy', $obat->kode) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus obat ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm rounded-2">
                                        <i class="bx bx-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal Edit Obat -->
                        <div class="modal fade" id="editObatModal{{ $obat->kode }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header bg-warning text-white">
                                        <h5 class="modal-title">Edit Data Obat</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('admin.obat.update', $obat->kode) }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body text-start">
                                            <div class="mb-3">
                                                <label for="nama" class="form-label">Nama Obat</label>
                                                <input type="text" class="form-control" name="nama" value="{{ old('nama', $obat->nama) }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="gambar" class="form-label">Gambar Obat (Kosongkan jika tidak diubah)</label>
                                                <input type="file" class="form-control" name="gambar">
                                            </div>
                                            <div class="mb-3">
                                                <label for="jenis_obat" class="form-label">Jenis Obat</label>
                                                <select class="form-select" name="jenis_obat" required>
                                                    <option value="Tablet" {{ $obat->jenis_obat == 'Tablet' ? 'selected' : '' }}>Tablet</option>
                                                    <option value="Kapsul" {{ $obat->jenis_obat == 'Kapsul' ? 'selected' : '' }}>Kapsul</option>
                                                    <option value="Sirup" {{ $obat->jenis_obat == 'Sirup' ? 'selected' : '' }}>Sirup</option>
                                                    <option value="Salep" {{ $obat->jenis_obat == 'Salep' ? 'selected' : '' }}>Salep</option>
                                                    <option value="Injeksi" {{ $obat->jenis_obat == 'Injeksi' ? 'selected' : '' }}>Injeksi</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="kategori" class="form-label">Kategori Obat</label>
                                                <select class="form-select" name="kategori" required>
                                                    <option value="Antibiotik" {{ $obat->kategori == 'Antibiotik' ? 'selected' : '' }}>Antibiotik</option>
                                                    <option value="Antipiretik" {{ $obat->kategori == 'Antipiretik' ? 'selected' : '' }}>Antipiretik</option>
                                                    <option value="Analgesik" {{ $obat->kategori == 'Analgesik' ? 'selected' : '' }}>Analgesik</option>
                                                    <option value="Antihistamin" {{ $obat->kategori == 'Antihistamin' ? 'selected' : '' }}>Antihistamin</option>
                                                    <option value="Vitamin" {{ $obat->kategori == 'Vitamin' ? 'selected' : '' }}>Vitamin</option>
                                                    <option value="Antiseptik" {{ $obat->kategori == 'Antiseptik' ? 'selected' : '' }}>Antiseptik</option>
                                                    <option value="Herbal" {{ $obat->kategori == 'Herbal' ? 'selected' : '' }}>Herbal</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="harga" class="form-label">Harga</label>
                                                <input type="number" class="form-control" name="harga" step="0.01" value="{{ old('harga', $obat->harga) }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="stok" class="form-label">Stok</label>
                                                <input type="number" class="form-control" name="stok" value="{{ old('stok', $obat->stok) }}" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                            <button type="submit" class="btn btn-primary">Update Obat</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">Tidak ada data obat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-4">
                {{ $obats->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <!-- Modal Tambah Obat -->
    <div class="modal fade" id="tambahObatModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Tambah Obat Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.obat.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="kode" class="form-label">Kode Obat</label>
                            <input type="text" class="form-control" name="kode" value="{{ old('kode') }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Obat</label>
                            <input type="text" class="form-control" name="nama" value="{{ old('nama') }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="gambar" class="form-label">Gambar Obat</label>
                            <input type="file" class="form-control" name="gambar" required>
                        </div>
                        <div class="mb-3">
                            <label for="jenis_obat" class="form-label">Jenis Obat</label>
                            <select class="form-select" name="jenis_obat" required>
                                <option value="Tablet">Tablet</option>
                                <option value="Kapsul">Kapsul</option>
                                <option value="Sirup">Sirup</option>
                                <option value="Salep">Salep</option>
                                <option value="Injeksi">Injeksi</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="kategori" class="form-label">Kategori Obat</label>
                            <select class="form-select" name="kategori" required>
                                <option value="Antibiotik">Antibiotik</option>
                                <option value="Antipiretik">Antipiretik</option>
                                <option value="Analgesik">Analgesik</option>
                                <option value="Antihistamin">Antihistamin</option>
                                <option value="Vitamin">Vitamin</option>
                                <option value="Antiseptik">Antiseptik</option>
                                <option value="Herbal">Herbal</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="harga" class="form-label">Harga</label>
                            <input type="number" class="form-control" name="harga" step="0.01" value="{{ old('harga') }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="stok" class="form-label">Stok</label>
                            <input type="number" class="form-control" name="stok" value="{{ old('stok') }}" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Tambah Obat</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
